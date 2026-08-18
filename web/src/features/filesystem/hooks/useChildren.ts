import { useInfiniteQuery } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { NodeSummary } from "../types";

interface ChildrenResponse {
  data: NodeSummary[];
  meta: { next_cursor: string | null };
}

export function useChildren(folderId: number) {
  return useInfiniteQuery({
    queryKey: ["nodes", folderId, "children"],
    queryFn: ({ pageParam }) =>
      client.get<ChildrenResponse>(
        `/nodes/${folderId}/children${pageParam ? `?cursor=${encodeURIComponent(pageParam)}` : ""}`,
      ),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
  });
}
