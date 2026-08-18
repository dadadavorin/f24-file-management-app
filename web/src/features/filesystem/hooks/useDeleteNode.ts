import { useMutation, useQueryClient } from "@tanstack/react-query";
import { client } from "../../../api/client";

export function useDeleteNode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => client.delete(`/nodes/${id}`),
    // Excludes the deleted node's own queries (still mounted when you delete
    // the folder you're inside) — refetching them would just retry into a
    // 404 for several seconds before the caller's onSuccess, which navigates
    // away, gets to run.
    onSuccess: (_data, id) =>
      queryClient.invalidateQueries({
        predicate: (query) => query.queryKey[0] === "nodes" && query.queryKey[1] !== id,
      }),
  });
}
