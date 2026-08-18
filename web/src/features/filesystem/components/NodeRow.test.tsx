import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { NodeRow } from "./NodeRow";
import type { NodeSummary } from "../types";

function node(overrides: Partial<NodeSummary> & Pick<NodeSummary, "id" | "name" | "type">): NodeSummary {
  return {
    parent_id: null,
    child_count: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

function renderRow(row: NodeSummary) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <NodeRow node={row} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("NodeRow", () => {
  it("links a folder to its listing and caps its child count at 99+", () => {
    renderRow(node({ id: 7, type: "folder", name: "Documents", child_count: 100 }));

    expect(screen.getByRole("link", { name: /Documents/ })).toHaveAttribute("href", "/folders/7");
    expect(screen.getByText("99+")).toBeInTheDocument();
  });

  it("renders a folder's exact child count below the cap", () => {
    renderRow(node({ id: 8, type: "folder", name: "Photos", child_count: 4 }));

    expect(screen.getByText("4")).toBeInTheDocument();
  });

  it("renders a file as plain text with no link and no child count", () => {
    renderRow(node({ id: 31, type: "file", name: "march.pdf" }));

    expect(screen.getByText("march.pdf")).toBeInTheDocument();
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("offers a delete action for every row", () => {
    renderRow(node({ id: 31, type: "file", name: "march.pdf" }));

    expect(screen.getByRole("button", { name: "Delete march.pdf" })).toBeInTheDocument();
  });
});
