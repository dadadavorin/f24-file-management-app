import { expect, test } from "@playwright/test";

test("create folder, descend, create file, find it by prefix, then delete the folder", async ({ page }) => {
  const run = Date.now();
  const folderName = `E2E Folder ${run}`;
  const stem = `e2e-report-${run}`;
  const fileName = `${stem}.txt`;
  const prefix = stem.slice(0, -4); // drop the last few timestamp digits — a genuine prefix, not the full name

  await page.goto("/");
  await expect(page.getByRole("navigation", { name: "Breadcrumb" })).toContainText("Root");

  await page.getByRole("button", { name: "New folder" }).click();
  await page.getByPlaceholder("Folder name").fill(folderName);
  await page.getByRole("button", { name: "Create" }).click();
  const folderLink = page.getByRole("link", { name: folderName });
  await expect(folderLink).toBeVisible();

  await folderLink.click();
  await expect(page.getByRole("navigation", { name: "Breadcrumb" })).toContainText(folderName);

  await page.getByRole("button", { name: "New file" }).click();
  await page.getByPlaceholder("File name").fill(fileName);
  await page.getByRole("button", { name: "Create" }).click();
  await expect(page.getByText(fileName, { exact: true })).toBeVisible();

  const searchInput = page.getByPlaceholder("Search files");
  await searchInput.fill(prefix);
  await expect(page.getByRole("option", { name: fileName })).toBeVisible();
  await page.keyboard.press("Escape");

  await page.getByRole("button", { name: "Delete this folder" }).click();
  await page.getByRole("dialog").getByRole("button", { name: "Delete", exact: true }).click();

  // Deleting the folder invalidates its own now-gone node query, which the
  // client retries three times with backoff before giving up and navigating
  // away — a real several-second wait, not test flakiness.
  await expect(page.getByRole("navigation", { name: "Breadcrumb" })).toContainText("Root", { timeout: 15_000 });
  await expect(page.getByRole("link", { name: folderName })).toHaveCount(0);
});
