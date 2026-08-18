import type { ReactNode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useSuggestions } from "./useSuggestions";
import { client } from "../../../api/client";

vi.mock("../../../api/client", () => ({
  client: { get: vi.fn() },
}));

const getMock = vi.mocked(client.get);

function wrapper(queryClient: QueryClient) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}

afterEach(() => {
  vi.clearAllMocks();
});

describe("useSuggestions", () => {
  it("never lets an older keystroke's response overwrite a newer one", async () => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    let resolveIn: (value: unknown) => void = () => {};
    const inPromise = new Promise((resolve) => {
      resolveIn = resolve;
    });
    let resolveInv: (value: unknown) => void = () => {};
    const invPromise = new Promise((resolve) => {
      resolveInv = resolve;
    });

    getMock.mockImplementation((path: string) => {
      const query = new URLSearchParams(path.split("?")[1]);
      if (query.get("q") === "in") return inPromise;
      if (query.get("q") === "inv") return invPromise;
      throw new Error(`unexpected request: ${path}`);
    });

    const { result, rerender } = renderHook(({ query }) => useSuggestions(query, "all", 1), {
      initialProps: { query: "in" },
      wrapper: wrapper(queryClient),
    });

    rerender({ query: "inv" });

    // The faster, later keystroke resolves first.
    resolveInv({ data: [{ id: 2, name: "invoice.pdf" }] });
    await waitFor(() => expect(result.current.data?.data).toEqual([{ id: 2, name: "invoice.pdf" }]));

    // The slower, earlier keystroke resolves after — it must land in its own
    // cache entry rather than clobbering "inv"'s.
    resolveIn({ data: [{ id: 1, name: "index.html" }] });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(result.current.data?.data).toEqual([{ id: 2, name: "invoice.pdf" }]);
  });

  it("issues no request for a blank query", () => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    renderHook(() => useSuggestions("   ", "all", 1), { wrapper: wrapper(queryClient) });

    expect(getMock).not.toHaveBeenCalled();
  });
});
