import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { NodeSummary, SearchScope } from "../types";

interface SuggestionsResponse {
  data: NodeSummary[];
}

/**
 * Backs the suggestion dropdown — one request per keystroke, after a 250ms
 * debounce. Stale responses are prevented by KEY ISOLATION, not cancellation:
 *
 *   type "in"   -> queryKey [...,"in"]   ---request A (slow)---\
 *   type "inv"  -> queryKey [...,"inv"]  ---request B (fast)---+--> both land
 *                                                                   in their
 *                                                                   OWN cache
 *                                                                   entry
 *
 *   TanStack Query only aborts an in-flight request on cancelQueries() or
 *   garbage collection — never because the key changed underneath it. If "in"
 *   and "inv" shared one key, A landing after B would silently overwrite B's
 *   fresher result. Because every keystroke gets its own key, there is
 *   nothing for A to overwrite — the dropdown just renders whichever key is
 *   selected right now, which is always the latest one typed.
 *
 *   placeholderData: keepPreviousData carries the previous key's result
 *   forward while the new key's request is in flight, so the dropdown
 *   doesn't flicker empty between keystrokes.
 */
export function useSuggestions(query: string, scope: SearchScope, parentId: number) {
  const trimmed = query.trim();

  return useQuery({
    queryKey: ["search", "suggestions", trimmed, scope, scope === "subtree" ? parentId : null],
    queryFn: () => {
      const params = new URLSearchParams({ q: trimmed, scope });
      if (scope === "subtree") {
        params.set("parent_id", String(parentId));
      }
      return client.get<SuggestionsResponse>(`/search/suggestions?${params.toString()}`);
    },
    enabled: trimmed !== "",
    placeholderData: keepPreviousData,
  });
}
