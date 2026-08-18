import { Navigate } from "react-router-dom";
import { Button } from "../../../components/ui/Button";
import { EmptyState } from "../../../components/ui/EmptyState";
import { Spinner } from "../../../components/ui/Spinner";
import { useRoot } from "../hooks/useRoot";

/**
 * The SPA has no hardcoded entry id — the root node's id comes from
 * GET /nodes/root, and "/" redirects to it once resolved.
 */
export function RootRedirect() {
  const { data, isPending, isError, refetch } = useRoot();

  if (isPending) {
    return (
      <div className="flex h-screen items-center justify-center">
        <Spinner size="lg" label="Loading the file system" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex h-screen items-center justify-center p-6">
        <EmptyState
          title="Couldn't load the file system"
          description="Something went wrong while contacting the server."
          action={<Button onClick={() => refetch()}>Try again</Button>}
        />
      </div>
    );
  }

  return <Navigate to={`/folders/${data.data.id}`} replace />;
}
