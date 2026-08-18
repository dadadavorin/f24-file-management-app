import { useLocation, useParams } from "react-router-dom";
import { EmptyState } from "../../../components/ui/EmptyState";
import { FolderView } from "./FolderView";

interface FolderPageState {
  fallbackChain?: number[];
}

export function FolderPage() {
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  const folderId = Number(id);

  if (!id || !Number.isInteger(folderId) || folderId < 1) {
    return (
      <div className="p-6">
        <EmptyState title="Folder not found" description="That folder id isn't valid." />
      </div>
    );
  }

  const fallbackChain = (location.state as FolderPageState | null)?.fallbackChain;

  return <FolderView folderId={folderId} fallbackChain={fallbackChain} />;
}
