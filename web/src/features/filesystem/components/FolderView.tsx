import { useEffect, useMemo, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { isApiError } from "../../../api/client";
import { Button } from "../../../components/ui/Button";
import { EmptyState } from "../../../components/ui/EmptyState";
import { Skeleton } from "../../../components/ui/Skeleton";
import { Spinner } from "../../../components/ui/Spinner";
import { useChildren } from "../hooks/useChildren";
import { useNode } from "../hooks/useNode";
import { Breadcrumbs } from "./Breadcrumbs";
import { CreateNodeDialog } from "./CreateNodeDialog";
import { DeleteConfirmDialog } from "./DeleteConfirmDialog";
import { NodeRow } from "./NodeRow";
import { SearchBox } from "./SearchBox";

export interface FolderViewProps {
  folderId: number;
  /**
   * Ancestor ids, nearest first, to try in turn if `folderId` turns out to be
   * gone — set when arriving here after deleting the folder previously in
   * view, whose own parent may since have been removed too.
   */
  fallbackChain?: number[];
  /** A node id to highlight — set when arriving here from a search result. */
  highlightId?: number;
}

export function FolderView({ folderId, fallbackChain = [], highlightId }: FolderViewProps) {
  const navigate = useNavigate();
  const node = useNode(folderId);
  const children = useChildren(folderId);
  const sentinelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!node.isError || fallbackChain.length === 0 || !isApiError(node.error, "NODE_NOT_FOUND")) {
      return;
    }

    const [next, ...rest] = fallbackChain;
    navigate(`/folders/${next}`, { replace: true, state: { fallbackChain: rest } });
  }, [node.isError, node.error, fallbackChain, navigate]);

  function navigateToNearestSurvivingAncestor() {
    if (!node.data) {
      return;
    }
    const chain = [...node.data.breadcrumbs].reverse().map((ancestor) => ancestor.id);
    const [next, ...rest] = chain;
    navigate(`/folders/${next}`, { replace: true, state: { fallbackChain: rest } });
  }

  const items = useMemo(() => children.data?.pages.flatMap((page) => page.data) ?? [], [children.data]);

  const hasNextPage = children.hasNextPage;
  const isFetchingNextPage = children.isFetchingNextPage;
  const fetchNextPage = children.fetchNextPage;

  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel || !hasNextPage) {
      return;
    }

    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        void fetchNextPage();
      }
    });

    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [hasNextPage, fetchNextPage]);

  useEffect(() => {
    if (highlightId === undefined) {
      return;
    }
    document.getElementById(`node-row-${highlightId}`)?.scrollIntoView({ block: "center" });
  }, [highlightId, items]);

  if (node.isPending || children.isPending) {
    return (
      <div className="p-6">
        <Skeleton className="mb-6 h-5 w-64" />
        <div className="flex flex-col gap-2">
          {Array.from({ length: 5 }, (_, index) => (
            <Skeleton key={index} className="h-12 w-full" />
          ))}
        </div>
      </div>
    );
  }

  if (node.isError && fallbackChain.length > 0 && isApiError(node.error, "NODE_NOT_FOUND")) {
    return (
      <div className="flex justify-center p-6">
        <Spinner size="lg" label="Loading" />
      </div>
    );
  }

  if (node.isError || children.isError) {
    return (
      <div className="p-6">
        <EmptyState
          title="Couldn't load this folder"
          description="Something went wrong while contacting the server."
          action={
            <Button
              onClick={() => {
                void node.refetch();
                void children.refetch();
              }}
            >
              Try again
            </Button>
          }
        />
      </div>
    );
  }

  const isRoot = node.data.data.parent_id === null;

  return (
    <div className="p-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Breadcrumbs breadcrumbs={node.data.breadcrumbs} current={node.data.data} />

        <div className="flex shrink-0 items-center gap-2">
          <CreateNodeDialog
            parentId={folderId}
            type="folder"
            trigger={
              <Button variant="secondary" size="sm">
                New folder
              </Button>
            }
          />
          <CreateNodeDialog
            parentId={folderId}
            type="file"
            trigger={
              <Button variant="secondary" size="sm">
                New file
              </Button>
            }
          />
          {!isRoot && (
            <DeleteConfirmDialog
              node={node.data.data}
              onDeleted={navigateToNearestSurvivingAncestor}
              trigger={
                <Button variant="destructive" size="sm">
                  Delete this folder
                </Button>
              }
            />
          )}
        </div>
      </div>

      <div className="mt-4">
        <SearchBox parentId={folderId} />
      </div>

      {items.length === 0 ? (
        <EmptyState
          className="mt-6"
          title="This folder is empty"
          description="Create a file or folder to get started."
        />
      ) : (
        <ul className="mt-6 flex flex-col divide-y divide-border rounded-lg border border-border">
          {items.map((item) => (
            <li key={item.id}>
              <NodeRow node={item} highlighted={item.id === highlightId} />
            </li>
          ))}
        </ul>
      )}

      {hasNextPage && (
        <div ref={sentinelRef} className="flex justify-center py-4">
          {isFetchingNextPage && <Spinner size="sm" label="Loading more" />}
        </div>
      )}
    </div>
  );
}
