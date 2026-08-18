import { Fragment } from "react";
import { Link } from "react-router-dom";
import type { NodeSummary } from "../types";

export interface BreadcrumbsProps {
  breadcrumbs: NodeSummary[];
  current: NodeSummary;
}

export function Breadcrumbs({ breadcrumbs, current }: BreadcrumbsProps) {
  return (
    <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
      {breadcrumbs.map((ancestor) => (
        <Fragment key={ancestor.id}>
          <Link to={`/folders/${ancestor.id}`} className="hover:text-foreground hover:underline">
            {ancestor.name}
          </Link>
          <span aria-hidden="true">/</span>
        </Fragment>
      ))}
      <span className="font-medium text-foreground">{current.name}</span>
    </nav>
  );
}
