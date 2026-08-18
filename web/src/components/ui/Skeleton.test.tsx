import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { Skeleton } from "./Skeleton";

describe("Skeleton", () => {
  it("renders a status placeholder", () => {
    render(<Skeleton className="h-4 w-32" />);

    expect(screen.getByRole("status")).toBeInTheDocument();
  });
});
