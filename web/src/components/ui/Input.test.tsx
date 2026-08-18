import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Input } from "./Input";

describe("Input", () => {
  it("accepts typed input", async () => {
    render(<Input aria-label="Name" />);

    const input = screen.getByLabelText("Name");
    await userEvent.type(input, "Invoices");

    expect(input).toHaveValue("Invoices");
  });

  it("marks itself invalid for assistive tech when invalid", () => {
    render(<Input aria-label="Name" invalid />);

    expect(screen.getByLabelText("Name")).toHaveAttribute("aria-invalid", "true");
  });
});
