import { useInfiniteQuery } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { FileSearchResult, SearchScope } from "../types";

interface SearchFilesResponse {
  data: FileSearchResult[];
  meta: { next_cursor: string | null };
}

export function useExactSearch(name: string, scope: SearchScope, parentId: number | null) {
  const trimmed = name.trim();

  return useInfiniteQuery({
    queryKey: ["search", "files", trimmed, scope, scope === "subtree" ? parentId : null],
    queryFn: ({ pageParam }) => {
      const params = new URLSearchParams({ name: trimmed, scope });
      if (scope === "subtree" && parentId !== null) {
        params.set("parent_id", String(parentId));
      }
      if (pageParam) {
        params.set("cursor", pageParam);
      }
      return client.get<SearchFilesResponse>(`/search/files?${params.toString()}`);
    },
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
    enabled: trimmed !== "",
  });
}
