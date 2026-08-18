import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { CreateNodeDialog } from "./CreateNodeDialog";
import { useChildren } from "../hooks/useChildren";
import { ApiError, client } from "../../../api/client";

vi.mock("../../../api/client", async () => {
  const actual = await vi.importActual<typeof import("../../../api/client")>("../../../api/client");
  return { ...actual, client: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } };
});

const getMock = vi.mocked(client.get);
const postMock = vi.mocked(client.post);

function createdFolder() {
  return {
    data: {
      id: 99,
      parent_id: 7,
      type: "folder",
      name: "Invoices",
      child_count: 0,
      created_at: "2026-01-01T00:00:00Z",
      updated_at: "2026-01-01T00:00:00Z",
    },
  };
}

// Mounts an active useChildren query alongside the dialog so a real
// invalidateQueries-triggered refetch can be observed, the same way it
// would happen inside FolderView.
function Harness() {
  useChildren(7);
  return <CreateNodeDialog parentId={7} type="folder" trigger={<button>New folder</button>} />;
}

function renderHarness() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <Harness />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  getMock.mockResolvedValue({ data: [], meta: { next_cursor: null } });
});

afterEach(() => {
  vi.clearAllMocks();
});

describe("CreateNodeDialog", () => {
  it("shows the server's field validation error under the name input", async () => {
    const user = userEvent.setup();
    postMock.mockRejectedValue(new ApiError("The given data was invalid.", 422, "VALIDATION_ERROR", { name: ["A name cannot be blank."] }));

    renderHarness();
    await user.click(screen.getByRole("button", { name: "New folder" }));
    await user.click(screen.getByRole("button", { name: "Create" }));

    expect(await screen.findByText("A name cannot be blank.")).toBeInTheDocument();
  });

  it("shows a friendly, type-aware message on a duplicate-name 409", async () => {
    const user = userEvent.setup();
    postMock.mockRejectedValue(
      new ApiError('A node named "Invoices" already exists in this folder.', 409, "DUPLICATE_NODE_NAME"),
    );

    renderHarness();
    await user.click(screen.getByRole("button", { name: "New folder" }));
    await user.type(screen.getByPlaceholderText("Folder name"), "Invoices");
    await user.click(screen.getByRole("button", { name: "Create" }));

    expect(await screen.findByText('A folder named "Invoices" already exists here.')).toBeInTheDocument();
  });

  it("closes the dialog and refetches the folder listing on success", async () => {
    const user = userEvent.setup();
    postMock.mockResolvedValue(createdFolder());

    renderHarness();
    await user.click(screen.getByRole("button", { name: "New folder" }));
    await user.type(screen.getByPlaceholderText("Folder name"), "Invoices");
    await user.click(screen.getByRole("button", { name: "Create" }));

    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
    await waitFor(() => expect(getMock.mock.calls.length).toBeGreaterThan(1));
  });

  it("submits once even when the create button is double-clicked", async () => {
    const user = userEvent.setup();
    let resolvePost!: (value: unknown) => void;
    postMock.mockReturnValue(new Promise((resolve) => (resolvePost = resolve)));

    renderHarness();
    await user.click(screen.getByRole("button", { name: "New folder" }));
    await user.type(screen.getByPlaceholderText("Folder name"), "Invoices");

    const createButton = screen.getByRole("button", { name: "Create" });
    await user.click(createButton);
    await user.click(createButton);

    resolvePost(createdFolder());
    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());

    expect(postMock).toHaveBeenCalledTimes(1);
  });

  it("closes on Escape and returns focus to the trigger", async () => {
    const user = userEvent.setup();
    renderHarness();

    const trigger = screen.getByRole("button", { name: "New folder" });
    await user.click(trigger);
    expect(screen.getByRole("dialog")).toBeInTheDocument();

    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});
