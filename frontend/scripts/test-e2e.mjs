import { spawnSync } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const frontendDirectory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const repositoryRoot = path.resolve(frontendDirectory, "..");
const supportUsername = "e2e-support";
const supportPassword = "e2e-local-only-password";
const composeFiles = [
  "-f",
  path.join(repositoryRoot, "docker-compose.yml"),
  "-f",
  path.join(repositoryRoot, "docker-compose.e2e.yml"),
];

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: repositoryRoot,
    encoding: "utf8",
    ...options,
  });

  if (result.error) {
    throw new Error(`Could not run ${command}: ${result.error.message}`);
  }

  if (result.status !== 0) {
    if (options.stdio === "inherit") {
      process.exit(result.status ?? 1);
    }

    throw new Error(`${command} exited with status ${result.status ?? "unknown"}.`);
  }

  return result.stdout ?? "";
}

console.log("Starting the isolated E2E Compose stack...");
run("docker", ["compose", ...composeFiles, "up", "-d", "--build", "--force-recreate", "backend", "frontend"], {
  stdio: "inherit",
});

const configCheck = `$values = [
  'provider' => getenv('AI_PROVIDER'),
  'model' => getenv('AI_MODEL'),
  'gemini_key_configured' => trim((string) getenv('GEMINI_API_KEY')) !== '',
  'openai_key_configured' => trim((string) getenv('OPENAI_API_KEY')) !== '',
  'gemini_base_host' => parse_url((string) getenv('GEMINI_BASE_URL'), PHP_URL_HOST),
  'app_key_configured' => trim((string) getenv('APP_KEY')) !== '',
  'support_username' => getenv('SUPPORT_USERNAME'),
  'support_password_configured' => trim((string) getenv('SUPPORT_PASSWORD')) !== '',
];
echo json_encode($values, JSON_THROW_ON_ERROR);`;
const configOutput = run("docker", [
  "compose",
  ...composeFiles,
  "exec",
  "-T",
  "backend",
  "php",
  "-r",
  configCheck,
]);

let config;
try {
  config = JSON.parse(configOutput);
} catch {
  throw new Error("Could not verify the backend E2E isolation settings.");
}

if (
  config.provider !== "gemini"
  || config.model !== "gemini-3.8-flash"
  || config.gemini_key_configured !== false
  || config.openai_key_configured !== false
  || config.app_key_configured !== true
  || !["127.0.0.1", "localhost", "::1"].includes(config.gemini_base_host)
) {
  throw new Error("The backend E2E AI isolation settings did not match the required safe configuration.");
}

console.log("E2E AI isolation verified: provider=gemini, model=gemini-3.8-flash, Gemini key=missing, OpenAI key=missing, Gemini URL=local/non-public.");

if (config.support_username !== supportUsername || config.support_password_configured !== true) {
  throw new Error("The E2E support authentication settings are not configured as expected.");
}

console.log(`E2E support authentication verified: username=${supportUsername}, password=configured.`);

async function waitFor(url, label) {
  const deadline = Date.now() + 120_000;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(url, { signal: AbortSignal.timeout(3_000) });
      if (response.ok) return;
    } catch {
      // The services may still be starting.
    }

    await delay(1_000);
  }

  throw new Error(`${label} did not become ready within 120 seconds.`);
}

await waitFor("http://localhost:8000/api/health", "Laravel/database health");
console.log("Laravel/database health verified. Seeding deterministic E2E fixtures...");
run("docker", [
  "compose",
  ...composeFiles,
  "exec",
  "-T",
  "backend",
  "php",
  "artisan",
  "db:seed",
  "--force",
], { stdio: "inherit" });

await waitFor("http://localhost:8000/api/orders/WN-1001", "seeded order WN-1001");
console.log("Seeded fixture WN-1001 is available.");
await waitFor("http://localhost:3000/", "Next.js frontend");

const apiBaseUrl = "http://localhost:8000/api";
const unauthenticatedList = await fetch(`${apiBaseUrl}/refund-requests`);
const loginResponse = await fetch(`${apiBaseUrl}/support/login`, {
  method: "POST",
  headers: { Accept: "application/json", "Content-Type": "application/json" },
  body: JSON.stringify({ username: supportUsername, password: supportPassword }),
});
const supportCookie = loginResponse.headers.get("set-cookie")?.split(";", 1)[0];

if (unauthenticatedList.status !== 401 || loginResponse.status !== 200 || !supportCookie) {
  throw new Error(`Support API login smoke failed (list=${unauthenticatedList.status}, login=${loginResponse.status}).`);
}

const authenticatedList = await fetch(`${apiBaseUrl}/refund-requests`, {
  headers: { Accept: "application/json", Cookie: supportCookie },
});
console.log(`Support API access smoke: unauthenticated=${unauthenticatedList.status}, authenticated=${authenticatedList.status}.`);

if (authenticatedList.status !== 200) {
  throw new Error(`Authenticated support API smoke failed with status ${authenticatedList.status}.`);
}

const publicOrder = await fetch(`${apiBaseUrl}/orders/WN-1001`);
if (!publicOrder.ok) throw new Error(`Public order lookup smoke failed with status ${publicOrder.status}.`);
const order = await publicOrder.json();
const publicSubmission = await fetch(`${apiBaseUrl}/refund-requests`, {
  method: "POST",
  headers: { Accept: "application/json", "Content-Type": "application/json" },
  body: JSON.stringify({
    order_id: order.id,
    requested_amount: order.total_amount,
    reason: "DAMAGED",
    customer_message: "Public customer submission verification for the support access boundary.",
  }),
});
console.log(`Public customer API smoke: order=${publicOrder.status}, refund submission=${publicSubmission.status}.`);

if (publicSubmission.status !== 201) {
  throw new Error(`Public customer refund submission smoke failed with status ${publicSubmission.status}.`);
}

const playwrightCli = path.join(frontendDirectory, "node_modules", "playwright", "cli.js");
run(process.execPath, [playwrightCli, "test"], {
  cwd: frontendDirectory,
  stdio: "inherit",
  env: {
    ...process.env,
    GEMINI_API_KEY: "",
    OPENAI_API_KEY: "",
    PLAYWRIGHT_BASE_URL: "http://localhost:3000",
  },
});
