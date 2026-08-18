import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Route, Routes, useLocation, useParams, useSearchParams } from "react-router-dom";
import { SearchBox } from "./SearchBox";
import { client } from "../../../api/client";

vi.mock("../../../api/client", () => ({
  client: { get: vi.fn() },
}));

const getMock = vi.mocked(client.get);

function FolderDestination() {
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  const highlightId = (location.state as { highlightId?: number } | null)?.highlightId;
  return (
    <p>
      Landed on folder {id}, highlight {highlightId ?? "none"}
    </p>
  );
}

function SearchDestination() {
  const [params] = useSearchParams();
  return <p>Search page: {params.toString()}</p>;
}

function renderSearchBox(parentId = 7) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={["/"]}>
        <Routes>
          <Route path="/" element={<SearchBox parentId={parentId} />} />
          <Route path="/folders/:id" element={<FolderDestination />} />
          <Route path="/search" element={<SearchDestination />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function fileResult(overrides: { id: number; parent_id: number; name: string }) {
  return {
    ...overrides,
    type: "file" as const,
    child_count: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  };
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("SearchBox", () => {
  it("issues no request for a blank query", async () => {
    renderSearchBox();

    await userEvent.click(screen.getByRole("combobox"));

    expect(getMock).not.toHaveBeenCalled();
  });

  it("debounces a burst of keystrokes into a single request", async () => {
    getMock.mockResolvedValue({ data: [fileResult({ id: 31, parent_id: 7, name: "invoice.pdf" })] });

    renderSearchBox();
    await userEvent.type(screen.getByRole("combobox"), "inv");

    await screen.findByText("invoice.pdf");
    expect(getMock).toHaveBeenCalledTimes(1);
    expect(getMock).toHaveBeenCalledWith(expect.stringContaining("q=inv"));
  });

  it("shows a no-results state and does not crash on a lone percent sign", async () => {
    getMock.mockResolvedValue({ data: [] });

    renderSearchBox();
    await userEvent.type(screen.getByRole("combobox"), "%");

    expect(await screen.findByText('No files match "%".')).toBeInTheDocument();
  });

  it("navigates to the containing folder and highlights the file on arrow-down then Enter", async () => {
    getMock.mockResolvedValue({ data: [fileResult({ id: 31, parent_id: 22, name: "march.pdf" })] });

    renderSearchBox();
    const input = screen.getByRole("combobox");
    await userEvent.type(input, "march");
    await screen.findByText("march.pdf");

    await userEvent.keyboard("{ArrowDown}{Enter}");

    expect(await screen.findByText("Landed on folder 22, highlight 31")).toBeInTheDocument();
  });

  it("clears the query and closes the dropdown", async () => {
    getMock.mockResolvedValue({ data: [fileResult({ id: 31, parent_id: 7, name: "march.pdf" })] });

    renderSearchBox();
    const input = screen.getByRole("combobox");
    await userEvent.type(input, "march");
    await screen.findByText("march.pdf");

    await userEvent.click(screen.getByRole("button", { name: "Clear search" }));

    expect(input).toHaveValue("");
    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
  });

  it("closes the dropdown on Escape", async () => {
    getMock.mockResolvedValue({ data: [fileResult({ id: 31, parent_id: 7, name: "march.pdf" })] });

    renderSearchBox();
    const input = screen.getByRole("combobox");
    await userEvent.type(input, "march");
    await screen.findByText("march.pdf");

    await userEvent.keyboard("{Escape}");

    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
  });

  it("submits an exact search for the typed text when Enter is pressed with no suggestion active", async () => {
    getMock.mockResolvedValue({ data: [] });

    renderSearchBox();
    const input = screen.getByRole("combobox");
    await userEvent.type(input, "report.pdf");
    await screen.findByText('No files match "report.pdf".');

    await userEvent.keyboard("{Enter}");

    expect(await screen.findByText(/Search page:/)).toHaveTextContent("name=report.pdf");
  });

  it("switches scope to everywhere and drops parent_id from the request", async () => {
    getMock.mockResolvedValue({ data: [] });

    renderSearchBox();
    await userEvent.click(screen.getByRole("button", { name: "Everywhere" }));
    await userEvent.type(screen.getByRole("combobox"), "x");

    await waitFor(() => expect(getMock).toHaveBeenCalled());
    const [path] = getMock.mock.calls[0] as [string];
    expect(path).toContain("scope=all");
    expect(path).not.toContain("parent_id");
  });
});
