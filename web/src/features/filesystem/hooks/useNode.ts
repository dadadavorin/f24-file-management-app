import { useQuery } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { NodeSummary } from "../types";

interface NodeShowResponse {
  data: NodeSummary;
  breadcrumbs: NodeSummary[];
}

export function useNode(id: number) {
  return useQuery({
    queryKey: ["nodes", id],
    queryFn: () => client.get<NodeShowResponse>(`/nodes/${id}`),
  });
}
