import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createMemoryRouter, RouterProvider } from "react-router-dom";
import { filesystemRoutes } from "./routes";
import { ApiError, client } from "../../api/client";
import type { NodeSummary } from "./types";

vi.mock("../../api/client", async () => {
  const actual = await vi.importActual<typeof import("../../api/client")>("../../api/client");
  return { ...actual, client: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } };
});

const getMock = vi.mocked(client.get);
const deleteMock = vi.mocked(client.delete);

const root: NodeSummary = {
  id: 1,
  parent_id: null,
  type: "folder",
  name: "Root",
  child_count: 1,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const documents: NodeSummary = {
  id: 7,
  parent_id: 1,
  type: "folder",
  name: "Documents",
  child_count: 1,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const invoices: NodeSummary = {
  id: 22,
  parent_id: 7,
  type: "folder",
  name: "Invoices",
  child_count: 0,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function mockApi() {
  getMock.mockImplementation((path: string) => {
    if (path === "/nodes/root") return Promise.resolve({ data: root });
    if (path === "/nodes/1/children") return Promise.resolve({ data: [documents], meta: { next_cursor: null } });
    if (path === "/nodes/1") return Promise.resolve({ data: root, breadcrumbs: [] });
    if (path === "/nodes/7/children") return Promise.resolve({ data: [], meta: { next_cursor: null } });
    if (path === "/nodes/7") return Promise.resolve({ data: documents, breadcrumbs: [root] });
    if (path === "/nodes/22/children") return Promise.resolve({ data: [], meta: { next_cursor: null } });
    if (path === "/nodes/22") return Promise.resolve({ data: invoices, breadcrumbs: [root, documents] });
    throw new Error(`unexpected request: ${path}`);
  });
}

function renderApp(initialPath: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const router = createMemoryRouter(filesystemRoutes, { initialEntries: [initialPath] });

  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );

  return router;
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("filesystem routes", () => {
  it("resolves a deep link straight to the target folder without visiting the root redirect", async () => {
    mockApi();

    renderApp("/folders/7");

    expect(await screen.findByText("This folder is empty")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Root" })).toHaveAttribute("href", "/folders/1");
  });

  it("navigates to the clicked breadcrumb's folder", async () => {
    mockApi();

    renderApp("/folders/7");

    await screen.findByText("This folder is empty");
    await userEvent.click(screen.getByRole("link", { name: "Root" }));

    expect(await screen.findByRole("link", { name: /Documents/ })).toBeInTheDocument();
  });

  it("restores the previous folder when the back button is pressed", async () => {
    mockApi();

    const router = renderApp("/folders/1");

    const documentsLink = await screen.findByRole("link", { name: /Documents/ });
    await userEvent.click(documentsLink);

    expect(await screen.findByText("This folder is empty")).toBeInTheDocument();

    router.navigate(-1);

    expect(await screen.findByRole("link", { name: /Documents/ })).toBeInTheDocument();
  });

  it("navigates to the parent after deleting the folder you are inside", async () => {
    mockApi();
    deleteMock.mockResolvedValue(undefined);

    renderApp("/folders/22");

    await userEvent.click(await screen.findByRole("button", { name: "Delete this folder" }));
    const dialog = await screen.findByRole("dialog", { name: 'Delete "Invoices"?' });
    await userEvent.click(within(dialog).getByRole("button", { name: "Delete" }));

    expect(await screen.findByText("Documents")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Root" })).toBeInTheDocument();
    expect(deleteMock).toHaveBeenCalledWith("/nodes/22");
  });

  it("skips an ancestor that was also removed and lands on the next surviving one", async () => {
    getMock.mockImplementation((path: string) => {
      if (path === "/nodes/22") return Promise.resolve({ data: invoices, breadcrumbs: [root, documents] });
      if (path === "/nodes/22/children") return Promise.resolve({ data: [], meta: { next_cursor: null } });
      if (path === "/nodes/7" || path === "/nodes/7/children") {
        return Promise.reject(new ApiError("Node 7 was not found.", 404, "NODE_NOT_FOUND"));
      }
      if (path === "/nodes/1") return Promise.resolve({ data: root, breadcrumbs: [] });
      if (path === "/nodes/1/children") return Promise.resolve({ data: [], meta: { next_cursor: null } });
      throw new Error(`unexpected request: ${path}`);
    });
    deleteMock.mockResolvedValue(undefined);

    renderApp("/folders/22");

    await userEvent.click(await screen.findByRole("button", { name: "Delete this folder" }));
    const dialog = await screen.findByRole("dialog", { name: 'Delete "Invoices"?' });
    await userEvent.click(within(dialog).getByRole("button", { name: "Delete" }));

    // 7 (Invoices' parent) was already gone too, so this lands on root, not 7.
    expect(await screen.findByText("Root")).toBeInTheDocument();
    expect(screen.queryByText("Couldn't load this folder")).not.toBeInTheDocument();
  });
});
