/**
 * The generated NodeResource types `child_count` as always-null (Scramble
 * can't see past the resource's when()) and the children endpoint's `data`
 * as `unknown[]` (Scramble can't infer a paginated resource collection).
 * This is the real shape both endpoints send — see NodeResource::toArray.
 */
export type NodeType = "folder" | "file";

export interface NodeSummary {
  id: number;
  parent_id: number | null;
  type: NodeType;
  name: string;
  child_count: number | null;
  created_at: string;
  updated_at: string;
}
