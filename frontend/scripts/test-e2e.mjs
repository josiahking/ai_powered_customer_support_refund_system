import { spawnSync } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const frontendDirectory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const repositoryRoot = path.resolve(frontendDirectory, "..");
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
  || !["127.0.0.1", "localhost", "::1"].includes(config.gemini_base_host)
) {
  throw new Error("The backend E2E AI isolation settings did not match the required safe configuration.");
}

console.log("E2E AI isolation verified: provider=gemini, model=gemini-3.8-flash, Gemini key=missing, OpenAI key=missing, Gemini URL=local/non-public.");

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
