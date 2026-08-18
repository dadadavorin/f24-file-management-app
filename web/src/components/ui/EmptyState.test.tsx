import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { EmptyState } from "./EmptyState";

describe("EmptyState", () => {
  it("renders the title, description, and action", () => {
    render(
      <EmptyState title="This folder is empty" description="Create a file or folder to get started." action={<button>New folder</button>} />,
    );

    expect(screen.getByText("This folder is empty")).toBeInTheDocument();
    expect(screen.getByText("Create a file or folder to get started.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "New folder" })).toBeInTheDocument();
  });
});
