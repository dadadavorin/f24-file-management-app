import { Link } from "react-router-dom";
import { cn } from "../../../lib/cn";
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

export interface NodeRowProps {
  node: NodeSummary;
}

export function NodeRow({ node }: NodeRowProps) {
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

  if (!isFolder) {
    return <div className={ROW_CLASSES}>{content}</div>;
  }

  return (
    <Link to={`/folders/${node.id}`} className={cn(ROW_CLASSES, "hover:bg-muted")}>
      {content}
    </Link>
  );
}
