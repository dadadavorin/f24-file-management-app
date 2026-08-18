import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { Breadcrumbs } from "./Breadcrumbs";
import type { NodeSummary } from "../types";

function node(overrides: Partial<NodeSummary> & Pick<NodeSummary, "id" | "name">): NodeSummary {
  return {
    parent_id: null,
    type: "folder",
    child_count: 0,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

describe("Breadcrumbs", () => {
  it("links every ancestor to its folder and renders the current folder as plain text", () => {
    render(
      <MemoryRouter>
        <Breadcrumbs
          breadcrumbs={[node({ id: 1, name: "Root" }), node({ id: 7, name: "Documents" })]}
          current={node({ id: 22, name: "Invoices" })}
        />
      </MemoryRouter>,
    );

    expect(screen.getByRole("link", { name: "Root" })).toHaveAttribute("href", "/folders/1");
    expect(screen.getByRole("link", { name: "Documents" })).toHaveAttribute("href", "/folders/7");

    expect(screen.getByText("Invoices")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Invoices" })).not.toBeInTheDocument();
  });
});
