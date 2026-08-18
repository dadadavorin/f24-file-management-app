import type { RouteObject } from "react-router-dom";
import { FolderPage } from "./components/FolderPage";
import { RootRedirect } from "./components/RootRedirect";
import { SearchResultsPage } from "./components/SearchResultsPage";

export const filesystemRoutes: RouteObject[] = [
  { path: "/", element: <RootRedirect /> },
  { path: "/folders/:id", element: <FolderPage /> },
  { path: "/search", element: <SearchResultsPage /> },
];
