import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from "./Dialog";

function ExampleDialog() {
  return (
    <Dialog>
      <DialogTrigger>Delete folder</DialogTrigger>
      <DialogContent>
        <DialogTitle>Delete "Invoices"?</DialogTitle>
        <DialogDescription>This folder and everything in it will be removed.</DialogDescription>
      </DialogContent>
    </Dialog>
  );
}

describe("Dialog", () => {
  it("opens on trigger click and closes on Escape", async () => {
    const user = userEvent.setup();
    render(<ExampleDialog />);

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Delete folder" }));
    expect(screen.getByRole("dialog", { name: 'Delete "Invoices"?' })).toBeInTheDocument();

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
