import { useParams } from "react-router-dom";

export function FolderPage() {
  const { id } = useParams<{ id: string }>();

  return (
    <div className="p-6">
      <p className="text-sm text-muted-foreground">Folder {id}</p>
    </div>
  );
}
