import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Button } from "./Button";

describe("Button", () => {
  it("renders its children and responds to clicks", async () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Create folder</Button>);

    const button = screen.getByRole("button", { name: "Create folder" });
    await userEvent.click(button);

    expect(onClick).toHaveBeenCalledOnce();
  });

  it("disables the button and shows a spinner while loading", () => {
    render(<Button loading>Create folder</Button>);

    const button = screen.getByRole("button", { name: /create folder/i });
    expect(button).toBeDisabled();
    expect(screen.getByRole("status")).toBeInTheDocument();
  });
});
