import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { SearchResultsView } from "./SearchResultsView";
import { client } from "../../../api/client";
import type { FileSearchResult } from "../types";

vi.mock("../../../api/client", () => ({
  client: { get: vi.fn() },
}));

const getMock = vi.mocked(client.get);

const invoicesFolder = {
  id: 22,
  parent_id: 7,
  type: "folder" as const,
  name: "Invoices",
  child_count: 0,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function result(id: number, name: string): FileSearchResult {
  return {
    id,
    parent_id: 22,
    type: "file",
    name,
    child_count: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    folder: invoicesFolder,
  };
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

function renderView(name = "report.pdf") {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <SearchResultsView name={name} scope="all" parentId={null} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

afterEach(() => {
  vi.clearAllMocks();
  intersectionCallback = null;
});

describe("SearchResultsView", () => {
  it("shows a zero-results state when nothing matches", async () => {
    getMock.mockResolvedValue({ data: [], meta: { next_cursor: null } });

    renderView();

    expect(await screen.findByText("No files found")).toBeInTheDocument();
  });

  it("lists matches with their containing folder label", async () => {
    getMock.mockResolvedValue({ data: [result(31, "report.pdf")], meta: { next_cursor: null } });

    renderView();

    const row = await screen.findByText("report.pdf");
    expect(within(row.closest("a") as HTMLElement).getByText("in Invoices")).toBeInTheDocument();
  });

  it("fetches the next page when the sentinel intersects", async () => {
    getMock.mockImplementation((path: string) =>
      path.includes("cursor=")
        ? Promise.resolve({ data: [result(32, "report-2.pdf")], meta: { next_cursor: null } })
        : Promise.resolve({ data: [result(31, "report.pdf")], meta: { next_cursor: "abc" } }),
    );

    renderView();

    expect(await screen.findByText("report.pdf")).toBeInTheDocument();
    expect(screen.queryByText("report-2.pdf")).not.toBeInTheDocument();

    intersectionCallback?.([{ isIntersecting: true }]);

    expect(await screen.findByText("report-2.pdf")).toBeInTheDocument();
  });
});
