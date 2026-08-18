import { Link } from "react-router-dom";
import type { FileSearchResult } from "../types";

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

export interface SearchResultRowProps {
  result: FileSearchResult;
}

export function SearchResultRow({ result }: SearchResultRowProps) {
  return (
    <Link
      to={`/folders/${result.parent_id}`}
      state={{ highlightId: result.id }}
      className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-muted"
    >
      <FileIcon />
      <span className="flex-1 truncate text-foreground">{result.name}</span>
      {result.folder && <span className="shrink-0 text-xs text-muted-foreground">in {result.folder.name}</span>}
    </Link>
  );
}
