import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { FolderView } from "./FolderView";
import { client } from "../../../api/client";
import type { NodeSummary } from "../types";

vi.mock("../../../api/client", () => ({
  client: { get: vi.fn() },
}));

const getMock = vi.mocked(client.get);

const documentsFolder: NodeSummary = {
  id: 7,
  parent_id: 1,
  type: "folder",
  name: "Documents",
  child_count: 2,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function folder(id: number, name: string): NodeSummary {
  return { ...documentsFolder, id, name };
}

function file(id: number, name: string): NodeSummary {
  return { id, parent_id: 7, type: "file", name, child_count: null, created_at: "2026-01-01T00:00:00Z", updated_at: "2026-01-01T00:00:00Z" };
}

type IntersectionCallback = (entries: Array<Pick<IntersectionObserverEntry, "isIntersecting">>) => void;

let intersectionCallback: IntersectionCallback | null = null;

class MockIntersectionObserver {
  constructor(callback: IntersectionCallback) {
    intersectionCallback = callback;
  }
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}

vi.stubGlobal("IntersectionObserver", MockIntersectionObserver);

function renderFolderView(folderId = 7) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <FolderView folderId={folderId} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

afterEach(() => {
  vi.clearAllMocks();
  intersectionCallback = null;
});

describe("FolderView", () => {
  it("shows loading skeletons while the folder and its children are pending", () => {
    getMock.mockReturnValue(new Promise(() => {}));

    renderFolderView();

    expect(screen.getAllByLabelText("Loading").length).toBeGreaterThan(0);
  });

  it("shows an empty state when the folder has no children", async () => {
    getMock.mockImplementation((path: string) =>
      path.includes("/children")
        ? Promise.resolve({ data: [], meta: { next_cursor: null } })
        : Promise.resolve({ data: documentsFolder, breadcrumbs: [] }),
    );

    renderFolderView();

    expect(await screen.findByText("This folder is empty")).toBeInTheDocument();
  });

  it("shows an error state and recovers on retry", async () => {
    getMock.mockRejectedValue(new Error("network error"));

    renderFolderView();

    expect(await screen.findByText("Couldn't load this folder")).toBeInTheDocument();

    getMock.mockImplementation((path: string) =>
      path.includes("/children")
        ? Promise.resolve({ data: [], meta: { next_cursor: null } })
        : Promise.resolve({ data: documentsFolder, breadcrumbs: [] }),
    );

    await userEvent.click(screen.getByRole("button", { name: "Try again" }));

    expect(await screen.findByText("This folder is empty")).toBeInTheDocument();
  });

  it("renders folders before files, in the order the API returns them", async () => {
    getMock.mockImplementation((path: string) =>
      path.includes("/children")
        ? Promise.resolve({
            data: [folder(8, "Invoices"), file(31, "march.pdf")],
            meta: { next_cursor: null },
          })
        : Promise.resolve({ data: documentsFolder, breadcrumbs: [] }),
    );

    renderFolderView();

    const rows = await screen.findAllByRole("listitem");
    expect(within(rows[0]).getByText("Invoices")).toBeInTheDocument();
    expect(within(rows[1]).getByText("march.pdf")).toBeInTheDocument();
  });

  it("fetches the next page when the sentinel intersects, and appends its rows", async () => {
    getMock.mockImplementation((path: string) => {
      if (path.includes("/children")) {
        return path.includes("cursor=")
          ? Promise.resolve({ data: [file(32, "notes.txt")], meta: { next_cursor: null } })
          : Promise.resolve({ data: [file(31, "march.pdf")], meta: { next_cursor: "abc" } });
      }
      return Promise.resolve({ data: documentsFolder, breadcrumbs: [] });
    });

    renderFolderView();

    expect(await screen.findByText("march.pdf")).toBeInTheDocument();
    expect(screen.queryByText("notes.txt")).not.toBeInTheDocument();

    expect(intersectionCallback).not.toBeNull();
    intersectionCallback?.([{ isIntersecting: true }]);

    expect(await screen.findByText("notes.txt")).toBeInTheDocument();
    expect(screen.getByText("march.pdf")).toBeInTheDocument();

    await waitFor(() => {
      const childrenCalls = getMock.mock.calls.filter(([path]) => (path as string).includes("/children"));
      expect(childrenCalls).toHaveLength(2);
    });
  });
});
