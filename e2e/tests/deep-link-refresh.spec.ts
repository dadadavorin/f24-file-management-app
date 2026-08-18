import { expect, test } from "@playwright/test";

test("a deep link to a nested folder survives a refresh", async ({ page }) => {
  const run = Date.now();
  const parentName = `E2E Parent ${run}`;
  const childName = `E2E Child ${run}`;
  const fileName = `nested-${run}.txt`;

  await page.goto("/");
  await page.getByRole("button", { name: "New folder" }).click();
  await page.getByPlaceholder("Folder name").fill(parentName);
  await page.getByRole("button", { name: "Create" }).click();
  await page.getByRole("link", { name: parentName }).click();

  await page.getByRole("button", { name: "New folder" }).click();
  await page.getByPlaceholder("Folder name").fill(childName);
  await page.getByRole("button", { name: "Create" }).click();
  await page.getByRole("link", { name: childName }).click();

  await page.getByRole("button", { name: "New file" }).click();
  await page.getByPlaceholder("File name").fill(fileName);
  await page.getByRole("button", { name: "Create" }).click();
  await expect(page.getByText(fileName, { exact: true })).toBeVisible();

  const nestedUrl = page.url();
  expect(nestedUrl).toMatch(/\/folders\/\d+$/);

  // A fresh navigation, not a client-side route change, simulates arriving via a shared link.
  await page.goto(nestedUrl);
  const breadcrumb = page.getByRole("navigation", { name: "Breadcrumb" });
  await expect(breadcrumb).toContainText(`Root/${parentName}/${childName}`);
  await expect(page.getByText(fileName, { exact: true })).toBeVisible();

  await page.reload();
  await expect(breadcrumb).toContainText(`Root/${parentName}/${childName}`);
  await expect(page.getByText(fileName, { exact: true })).toBeVisible();
});
