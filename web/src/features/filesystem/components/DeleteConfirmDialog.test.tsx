import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { DeleteConfirmDialog } from "./DeleteConfirmDialog";
import { useChildren } from "../hooks/useChildren";
import { client } from "../../../api/client";
import type { NodeSummary } from "../types";

vi.mock("../../../api/client", async () => {
  const actual = await vi.importActual<typeof import("../../../api/client")>("../../../api/client");
  return { ...actual, client: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } };
});

const getMock = vi.mocked(client.get);
const deleteMock = vi.mocked(client.delete);

const invoicesFolder: NodeSummary = {
  id: 22,
  parent_id: 7,
  type: "folder",
  name: "Invoices",
  child_count: 3,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const emptyFolder: NodeSummary = { ...invoicesFolder, id: 23, name: "Photos", child_count: 0 };

// Mounts an active useChildren query alongside the dialog so a real
// invalidateQueries-triggered refetch can be observed, the same way it
// would happen inside FolderView.
function Harness({ node }: { node: NodeSummary }) {
  useChildren(node.parent_id ?? 1);
  return <DeleteConfirmDialog node={node} trigger={<button>Delete row</button>} />;
}

function renderHarness(node: NodeSummary = invoicesFolder) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <Harness node={node} />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  getMock.mockResolvedValue({ data: [], meta: { next_cursor: null } });
});

afterEach(() => {
  vi.clearAllMocks();
});

describe("DeleteConfirmDialog", () => {
  it("names the node being deleted and warns when a folder has contents", async () => {
    const user = userEvent.setup();
    renderHarness(invoicesFolder);
    await user.click(screen.getByRole("button", { name: "Delete row" }));

    expect(screen.getByRole("dialog", { name: 'Delete "Invoices"?' })).toBeInTheDocument();
    expect(screen.getByText(/isn't empty/)).toBeInTheDocument();
  });

  it("does not warn about contents for an empty folder", async () => {
    const user = userEvent.setup();
    renderHarness(emptyFolder);
    await user.click(screen.getByRole("button", { name: "Delete row" }));

    expect(screen.getByRole("dialog", { name: 'Delete "Photos"?' })).toBeInTheDocument();
    expect(screen.queryByText(/isn't empty/)).not.toBeInTheDocument();
  });

  it("closes the dialog and refetches the listing on success", async () => {
    const user = userEvent.setup();
    deleteMock.mockResolvedValue(undefined);

    renderHarness(invoicesFolder);
    await user.click(screen.getByRole("button", { name: "Delete row" }));
    await user.click(screen.getByRole("button", { name: "Delete" }));

    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
    await waitFor(() => expect(getMock.mock.calls.length).toBeGreaterThan(1));
  });

  it("confirms once even when the delete button is double-clicked", async () => {
    const user = userEvent.setup();
    let resolveDelete!: () => void;
    deleteMock.mockReturnValue(new Promise<void>((resolve) => (resolveDelete = resolve)));

    renderHarness(invoicesFolder);
    await user.click(screen.getByRole("button", { name: "Delete row" }));

    const confirmButton = screen.getByRole("button", { name: "Delete" });
    await user.click(confirmButton);
    await user.click(confirmButton);

    resolveDelete();
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());

    expect(deleteMock).toHaveBeenCalledTimes(1);
  });

  it("closes on Escape and returns focus to the trigger", async () => {
    const user = userEvent.setup();
    renderHarness(invoicesFolder);

    const trigger = screen.getByRole("button", { name: "Delete row" });
    await user.click(trigger);
    expect(screen.getByRole("dialog")).toBeInTheDocument();

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});
