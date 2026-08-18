import { Link } from "react-router-dom";
import { cn } from "../../../lib/cn";
import { DeleteConfirmDialog } from "./DeleteConfirmDialog";
import type { NodeSummary } from "../types";

const ROW_CLASSES = "flex items-center gap-3 px-4 py-3 text-sm";

function formatChildCount(count: number): string {
  return count >= 100 ? "99+" : String(count);
}

function FolderIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true">
      <path d="M2 5.5A1.5 1.5 0 0 1 3.5 4h4.379a1.5 1.5 0 0 1 1.06.44l1.122 1.12A1.5 1.5 0 0 0 11.12 6H16.5A1.5 1.5 0 0 1 18 7.5v7A1.5 1.5 0 0 1 16.5 16h-13A1.5 1.5 0 0 1 2 14.5v-9Z" />
    </svg>
  );
}

function FileIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true">
      <path
        fillRule="evenodd"
        d="M4 2.5A1.5 1.5 0 0 1 5.5 1h5.379a1.5 1.5 0 0 1 1.06.44l3.122 3.12a1.5 1.5 0 0 1 .439 1.061V17.5A1.5 1.5 0 0 1 14 19H5.5A1.5 1.5 0 0 1 4 17.5v-15Z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function TrashIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
      <path
        fillRule="evenodd"
        d="M8.75 1a.75.75 0 0 0-.75.75V3H4.5a.75.75 0 0 0 0 1.5h.35l.7 10.15A2 2 0 0 0 7.54 16.5h4.92a2 2 0 0 0 1.99-1.85l.7-10.15h.35a.75.75 0 0 0 0-1.5h-3.5V1.75a.75.75 0 0 0-.75-.75h-2.5ZM8.5 6.75a.75.75 0 0 1 1.5 0v6.5a.75.75 0 0 1-1.5 0v-6.5Zm3.25-.75a.75.75 0 0 0-.75.75v6.5a.75.75 0 0 0 1.5 0v-6.5a.75.75 0 0 0-.75-.75Z"
        clipRule="evenodd"
      />
    </svg>
  );
}

export interface NodeRowProps {
  node: NodeSummary;
  highlighted?: boolean;
}

export function NodeRow({ node, highlighted = false }: NodeRowProps) {
  const isFolder = node.type === "folder";

  const content = (
    <>
      {isFolder ? <FolderIcon /> : <FileIcon />}
      <span className="flex-1 truncate text-foreground">{node.name}</span>
      {isFolder && node.child_count !== null && (
        <span className="text-xs text-muted-foreground">{formatChildCount(node.child_count)}</span>
      )}
    </>
  );

  return (
    <div
      id={`node-row-${node.id}`}
      className={cn(ROW_CLASSES, "justify-between gap-2", highlighted && "bg-primary/10 ring-2 ring-inset ring-primary/60")}
    >
      {isFolder ? (
        <Link to={`/folders/${node.id}`} className="flex min-w-0 flex-1 items-center gap-3 hover:underline">
          {content}
        </Link>
      ) : (
        <div className="flex min-w-0 flex-1 items-center gap-3">{content}</div>
      )}
      <DeleteConfirmDialog
        node={node}
        trigger={
          <button
            type="button"
            aria-label={`Delete ${node.name}`}
            className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-destructive"
          >
            <TrashIcon />
          </button>
        }
      />
    </div>
  );
}
