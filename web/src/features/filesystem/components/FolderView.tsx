import { useEffect, useMemo, useRef } from "react";
import { Button } from "../../../components/ui/Button";
import { EmptyState } from "../../../components/ui/EmptyState";
import { Skeleton } from "../../../components/ui/Skeleton";
import { Spinner } from "../../../components/ui/Spinner";
import { useChildren } from "../hooks/useChildren";
import { useNode } from "../hooks/useNode";
import { Breadcrumbs } from "./Breadcrumbs";
import { NodeRow } from "./NodeRow";

export interface FolderViewProps {
  folderId: number;
}

export function FolderView({ folderId }: FolderViewProps) {
  const node = useNode(folderId);
  const children = useChildren(folderId);
  const sentinelRef = useRef<HTMLDivElement>(null);

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

  return (
    <div className="p-6">
      <Breadcrumbs breadcrumbs={node.data.breadcrumbs} current={node.data.data} />

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
              <NodeRow node={item} />
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
