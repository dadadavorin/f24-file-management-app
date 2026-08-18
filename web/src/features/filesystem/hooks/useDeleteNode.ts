import { useMutation, useQueryClient } from "@tanstack/react-query";
import { client } from "../../../api/client";

export function useDeleteNode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => client.delete(`/nodes/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["nodes"] }),
  });
}
