import { useEffect, useMemo, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "../../../components/ui/Button";
import { EmptyState } from "../../../components/ui/EmptyState";
import { Skeleton } from "../../../components/ui/Skeleton";
import { Spinner } from "../../../components/ui/Spinner";
import { useExactSearch } from "../hooks/useExactSearch";
import type { SearchScope } from "../types";
import { SearchResultRow } from "./SearchResultRow";

export interface SearchResultsViewProps {
  name: string;
  scope: SearchScope;
  parentId: number | null;
}

export function SearchResultsView({ name, scope, parentId }: SearchResultsViewProps) {
  const navigate = useNavigate();
  const results = useExactSearch(name, scope, parentId);
  const sentinelRef = useRef<HTMLDivElement>(null);

  const items = useMemo(() => results.data?.pages.flatMap((page) => page.data) ?? [], [results.data]);

  const hasNextPage = results.hasNextPage;
  const isFetchingNextPage = results.isFetchingNextPage;
  const fetchNextPage = results.fetchNextPage;

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

  if (results.isPending) {
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

  if (results.isError) {
    return (
      <div className="p-6">
        <EmptyState
          title="Couldn't load search results"
          description="Something went wrong while contacting the server."
          action={<Button onClick={() => void results.refetch()}>Try again</Button>}
        />
      </div>
    );
  }

  return (
    <div className="p-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-sm text-muted-foreground">
          Results for <span className="font-medium text-foreground">"{name}"</span>
        </h1>
        <Button variant="secondary" size="sm" onClick={() => navigate(-1)}>
          Back
        </Button>
      </div>

      {items.length === 0 ? (
        <EmptyState className="mt-6" title="No files found" description={`Nothing matches "${name}".`} />
      ) : (
        <ul className="mt-6 flex flex-col divide-y divide-border rounded-lg border border-border">
          {items.map((item) => (
            <li key={item.id}>
              <SearchResultRow result={item} />
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
