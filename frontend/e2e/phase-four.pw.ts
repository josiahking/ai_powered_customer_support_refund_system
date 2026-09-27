import { expect, test, type Page, type Response as PlaywrightResponse, type TestInfo } from "@playwright/test";

const customerMessage = "The product arrived with the casing cracked.";

async function submitRequest(page: Page, orderNumber: string, testInfo: TestInfo): Promise<PlaywrightResponse> {
  await page.goto("/");
  await page.getByLabel("Order number").fill(orderNumber);
  await page.getByRole("button", { name: "Find order" }).click();
  await expect(page.getByRole("region", { name: "Order found" })).toBeVisible();

  await page.getByLabel(/What best describes the issue/).selectOption("DAMAGED");
  await page.getByLabel("Tell us what happened").fill(customerMessage);
  await page.screenshot({ path: testInfo.outputPath(`refund-form-${orderNumber}.png`), fullPage: true });

  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === "POST" && new URL(response.url()).pathname === "/api/refund-requests",
  );
  await page.getByRole("button", { name: "Submit refund request" }).click();
  return responsePromise;
}

test("customer receives a deterministic final-sale denial", async ({ page }, testInfo) => {
  const response = await submitRequest(page, "WN-1008", testInfo);
  expect(response.ok()).toBeTruthy();
  const result = await response.json();

  await expect(page.getByRole("region", { name: "Refund decision" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "DENIED" })).toBeVisible();
  await expect(page.getByText(/marked final sale/i)).toBeVisible();
  await expect(page.getByText(/final decision is determined by refund policy/i)).toBeVisible();
  expect(result.reason_code).toBe("FINAL_SALE");
  await page.screenshot({ path: testInfo.outputPath("denied-result.png"), fullPage: true });
});

test("AI outage escalates and remains visible in the support audit", async ({ page }, testInfo) => {
  const response = await submitRequest(page, "WN-1001", testInfo);
  expect(response.ok()).toBeTruthy();
  const result = await response.json();

  expect(result.outcome).toBe("ESCALATED");
  expect(result.ai_analysis_status).toBe("UNAVAILABLE");
  await expect(page.getByRole("heading", { name: "ESCALATED" })).toBeVisible();
  await expect(page.getByText(/support specialist will review your request/i)).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath("escalated-result.png"), fullPage: true });

  await page.goto("/support");
  await expect(page.getByRole("heading", { name: "Support dashboard" })).toBeVisible();
  await page.getByRole("link", { name: `#${result.id}` }).click();
  await expect(page).toHaveURL(new RegExp(`/support/refunds/${result.id}$`));

  await expect(page.getByRole("region", { name: "Customer request" })).toBeVisible();
  await expect(page.getByRole("region", { name: "AI analysis" })).toContainText("unavailable");
  await expect(page.getByRole("region", { name: "Policy evaluation" })).toBeVisible();
  await expect(page.getByRole("region", { name: "Final resolution" })).toContainText("ESCALATED");
  await expect(page.getByText(/policy evaluation shown above remains the deterministic fallback result/i)).toBeVisible();
});

test("customer, dashboard, and audit routes fit desktop and mobile viewports", async ({ page }, testInfo) => {
  const pageErrors: string[] = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));
  page.on("console", (message) => {
    if (message.type() === "error") pageErrors.push(message.text());
  });

  await page.goto("/support");
  const detailLink = page.locator('a[href^="/support/refunds/"]').first();
  await expect(detailLink).toBeVisible();
  const detailPath = await detailLink.getAttribute("href");
  if (!detailPath) throw new Error("Seeded support row did not include a detail link.");

  for (const viewport of [
    { name: "desktop", width: 1440, height: 900 },
    { name: "mobile", width: 375, height: 812 },
  ]) {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });

    for (const route of ["/", "/support", detailPath]) {
      await page.goto(route);
      await expect(page.locator("main")).toBeVisible();
      const widths = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      }));
      expect(widths.document, `${route} document overflow at ${viewport.name}`).toBeLessThanOrEqual(widths.viewport);
      expect(widths.body, `${route} body overflow at ${viewport.name}`).toBeLessThanOrEqual(widths.viewport);
      expect(pageErrors, `browser errors at ${route} (${viewport.name})`).toEqual([]);
      await page.screenshot({ path: testInfo.outputPath(`${viewport.name}-${route.replaceAll("/", "_") || "home"}.png`), fullPage: true });
    }
  }
});
