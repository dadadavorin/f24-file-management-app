import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { Spinner } from "./Spinner";

describe("Spinner", () => {
  it("exposes a status role for assistive tech", () => {
    render(<Spinner label="Loading folder" />);

    expect(screen.getByRole("status", { name: "Loading folder" })).toBeInTheDocument();
  });
});
