import { useParams } from "react-router-dom";
import { EmptyState } from "../../../components/ui/EmptyState";
import { FolderView } from "./FolderView";

export function FolderPage() {
  const { id } = useParams<{ id: string }>();
  const folderId = Number(id);

  if (!id || !Number.isInteger(folderId) || folderId < 1) {
    return (
      <div className="p-6">
        <EmptyState title="Folder not found" description="That folder id isn't valid." />
      </div>
    );
  }

  return <FolderView folderId={folderId} />;
}
