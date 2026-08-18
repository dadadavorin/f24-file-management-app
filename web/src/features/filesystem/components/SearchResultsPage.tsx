import { useSearchParams } from "react-router-dom";
import { EmptyState } from "../../../components/ui/EmptyState";
import type { SearchScope } from "../types";
import { SearchResultsView } from "./SearchResultsView";

export function SearchResultsPage() {
  const [searchParams] = useSearchParams();
  const name = searchParams.get("name") ?? "";
  const scope: SearchScope = searchParams.get("scope") === "subtree" ? "subtree" : "all";
  const parentIdParam = searchParams.get("parent_id");
  const parentId = parentIdParam !== null && Number.isInteger(Number(parentIdParam)) ? Number(parentIdParam) : null;

  if (name.trim() === "" || (scope === "subtree" && parentId === null)) {
    return (
      <div className="p-6">
        <EmptyState title="No search to show" description="Start a search from a folder to see results here." />
      </div>
    );
  }

  return <SearchResultsView name={name} scope={scope} parentId={parentId} />;
}
