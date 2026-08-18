import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
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

describe("NodeRow", () => {
  it("links a folder to its listing and caps its child count at 99+", () => {
    render(
      <MemoryRouter>
        <NodeRow node={node({ id: 7, type: "folder", name: "Documents", child_count: 100 })} />
      </MemoryRouter>,
    );

    expect(screen.getByRole("link", { name: /Documents/ })).toHaveAttribute("href", "/folders/7");
    expect(screen.getByText("99+")).toBeInTheDocument();
  });

  it("renders a folder's exact child count below the cap", () => {
    render(
      <MemoryRouter>
        <NodeRow node={node({ id: 8, type: "folder", name: "Photos", child_count: 4 })} />
      </MemoryRouter>,
    );

    expect(screen.getByText("4")).toBeInTheDocument();
  });

  it("renders a file as plain text with no link and no child count", () => {
    render(
      <MemoryRouter>
        <NodeRow node={node({ id: 31, type: "file", name: "march.pdf" })} />
      </MemoryRouter>,
    );

    expect(screen.getByText("march.pdf")).toBeInTheDocument();
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });
});
