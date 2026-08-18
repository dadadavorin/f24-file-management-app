import { afterEach, describe, expect, it, vi } from "vitest";
import { act, renderHook } from "@testing-library/react";
import { useDebouncedValue } from "./useDebouncedValue";

afterEach(() => {
  vi.useRealTimers();
});

describe("useDebouncedValue", () => {
  it("holds the previous value until the delay elapses", () => {
    vi.useFakeTimers();
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 250), {
      initialProps: { value: "a" },
    });

    rerender({ value: "ab" });
    expect(result.current).toBe("a");

    act(() => vi.advanceTimersByTime(249));
    expect(result.current).toBe("a");

    act(() => vi.advanceTimersByTime(1));
    expect(result.current).toBe("ab");
  });

  it("restarts the delay on every change, so a burst of updates settles once", () => {
    vi.useFakeTimers();
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 250), {
      initialProps: { value: "i" },
    });

    rerender({ value: "in" });
    act(() => vi.advanceTimersByTime(200));
    rerender({ value: "inv" });
    act(() => vi.advanceTimersByTime(200));

    expect(result.current).toBe("i");

    act(() => vi.advanceTimersByTime(50));
    expect(result.current).toBe("inv");
  });
});
