import { useQuery } from "@tanstack/react-query";
import { client } from "../../../api/client";
import type { operations } from "../../../api/generated/types";

type RootResponse = operations["node.root"]["responses"][200]["content"]["application/json"];

export function useRoot() {
  return useQuery({
    queryKey: ["nodes", "root"],
    queryFn: () => client.get<RootResponse>("/nodes/root"),
  });
}
