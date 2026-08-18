import { useMutation, useQueryClient } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { operations } from "../../../api/generated/types";

type CreateNodeBody = operations["node.store"]["requestBody"]["content"]["application/json"];
type CreateNodeResponse = operations["node.store"]["responses"][201]["content"]["application/json"];

export function useCreateNode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: CreateNodeBody) => client.post<CreateNodeResponse>("/nodes", body),
    // Invalidates every node query rather than just the parent's children —
    // the new row can affect a listing's folders-first ordering and the
    // parent's own child_count wherever it's shown.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["nodes"] }),
  });
}
