import { type KeyboardEvent, useEffect, useId, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Input } from "../../../components/ui/Input";
import { Spinner } from "../../../components/ui/Spinner";
import { cn } from "../../../lib/cn";
import { useDebouncedValue } from "../../../lib/useDebouncedValue";
import { useSuggestions } from "../hooks/useSuggestions";
import type { NodeSummary, SearchScope } from "../types";

export interface SearchBoxProps {
  /** Current folder — the subtree root when scope is "subtree". */
  parentId: number;
}

const SCOPE_OPTIONS: Array<{ value: SearchScope; label: string }> = [
  { value: "subtree", label: "This folder" },
  { value: "all", label: "Everywhere" },
];

function buildSearchHref(name: string, scope: SearchScope, parentId: number): string {
  const params = new URLSearchParams({ name, scope });
  if (scope === "subtree") {
    params.set("parent_id", String(parentId));
  }
  return `/search?${params.toString()}`;
}

function ClearIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
      <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
    </svg>
  );
}

export function SearchBox({ parentId }: SearchBoxProps) {
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [scope, setScope] = useState<SearchScope>("subtree");
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const inputRef = useRef<HTMLInputElement>(null);
  const listboxId = useId();

  const trimmedQuery = query.trim();
  const debouncedQuery = useDebouncedValue(query, 250);
  const debouncedTrimmed = debouncedQuery.trim();
  const suggestions = useSuggestions(debouncedQuery, scope, parentId);
  const results = useMemo(() => suggestions.data?.data ?? [], [suggestions.data]);

  useEffect(() => {
    setActiveIndex(-1);
  }, [debouncedTrimmed, scope]);

  const isSettled = debouncedTrimmed === trimmedQuery;
  const isLoading = trimmedQuery !== "" && (!isSettled || (suggestions.isFetching && results.length === 0));
  const hasNoResults = trimmedQuery !== "" && isSettled && !suggestions.isFetching && results.length === 0;
  const showDropdown = isOpen && trimmedQuery !== "";

  function reset() {
    setQuery("");
    setIsOpen(false);
    setActiveIndex(-1);
  }

  function selectResult(result: NodeSummary) {
    reset();
    navigate(`/folders/${result.parent_id}`, { state: { highlightId: result.id } });
  }

  function viewAllResults() {
    if (trimmedQuery === "") {
      return;
    }
    const href = buildSearchHref(trimmedQuery, scope, parentId);
    reset();
    navigate(href);
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      if (results.length > 0) {
        setIsOpen(true);
        setActiveIndex((index) => Math.min(index + 1, results.length - 1));
      }
      return;
    }
    if (event.key === "ArrowUp") {
      event.preventDefault();
      setActiveIndex((index) => Math.max(index - 1, -1));
      return;
    }
    if (event.key === "Enter") {
      event.preventDefault();
      const active = activeIndex >= 0 ? results[activeIndex] : undefined;
      if (active) {
        selectResult(active);
      } else {
        viewAllResults();
      }
      return;
    }
    if (event.key === "Escape" && isOpen) {
      event.preventDefault();
      setIsOpen(false);
      setActiveIndex(-1);
    }
  }

  return (
    <div
      className="relative w-full max-w-sm"
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setIsOpen(false);
        }
      }}
    >
      <div className="relative">
        <Input
          ref={inputRef}
          role="combobox"
          aria-expanded={showDropdown}
          aria-controls={listboxId}
          aria-autocomplete="list"
          aria-activedescendant={activeIndex >= 0 ? `${listboxId}-${activeIndex}` : undefined}
          placeholder="Search files"
          value={query}
          onChange={(event) => {
            setQuery(event.target.value);
            setIsOpen(true);
          }}
          onFocus={() => {
            if (trimmedQuery !== "") {
              setIsOpen(true);
            }
          }}
          onKeyDown={handleKeyDown}
        />
        {query !== "" && (
          <button
            type="button"
            aria-label="Clear search"
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
            onClick={() => {
              reset();
              inputRef.current?.focus();
            }}
          >
            <ClearIcon />
          </button>
        )}
      </div>

      <div className="mt-2 flex gap-1">
        {SCOPE_OPTIONS.map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={scope === option.value}
            onClick={() => setScope(option.value)}
            className={cn(
              "rounded-full px-2.5 py-1 text-xs",
              scope === option.value
                ? "bg-primary text-primary-foreground"
                : "bg-muted text-muted-foreground hover:text-foreground",
            )}
          >
            {option.label}
          </button>
        ))}
      </div>

      {showDropdown && (
        <ul
          id={listboxId}
          role="listbox"
          aria-label="Search suggestions"
          className="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-border bg-background shadow-lg"
        >
          {isLoading && (
            <li className="flex items-center justify-center p-4">
              <Spinner size="sm" label="Searching" />
            </li>
          )}
          {!isLoading && hasNoResults && (
            <li className="p-4 text-sm text-muted-foreground">No files match "{trimmedQuery}".</li>
          )}
          {!isLoading &&
            results.map((result, index) => (
              <li
                key={result.id}
                id={`${listboxId}-${index}`}
                role="option"
                aria-selected={index === activeIndex}
                className={cn(
                  "cursor-pointer px-3 py-2 text-sm text-foreground",
                  index === activeIndex ? "bg-muted" : "hover:bg-muted",
                )}
                onMouseDown={(event) => {
                  event.preventDefault();
                  selectResult(result);
                }}
                onMouseEnter={() => setActiveIndex(index)}
              >
                {result.name}
              </li>
            ))}
          {!isLoading && trimmedQuery !== "" && (
            <li className="border-t border-border">
              <button
                type="button"
                className="w-full px-3 py-2 text-left text-sm text-primary hover:bg-muted"
                onMouseDown={(event) => {
                  event.preventDefault();
                  viewAllResults();
                }}
              >
                See all results for "{trimmedQuery}"
              </button>
            </li>
          )}
        </ul>
      )}
    </div>
  );
}
