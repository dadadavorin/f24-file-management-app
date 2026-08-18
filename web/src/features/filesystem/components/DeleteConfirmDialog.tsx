import { type ReactNode, useRef, useState } from "react";
import { isApiError } from "../../../api/client";
import { Button } from "../../../components/ui/Button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "../../../components/ui/Dialog";
import { useDeleteNode } from "../hooks/useDeleteNode";
import type { NodeSummary } from "../types";

export interface DeleteConfirmDialogProps {
  node: NodeSummary;
  trigger: ReactNode;
  /** Called after a successful delete — e.g. to navigate away when the deleted node is the folder being viewed. */
  onDeleted?: () => void;
}

function errorMessage(error: unknown): string {
  if (isApiError(error, "ROOT_IS_IMMUTABLE")) {
    return "The root folder can't be deleted.";
  }
  if (isApiError(error, "NODE_NOT_FOUND")) {
    return "This was already deleted.";
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Something went wrong. Try again.";
}

export function DeleteConfirmDialog({ node, trigger, onDeleted }: DeleteConfirmDialogProps) {
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const deleteNode = useDeleteNode();
  const submittingRef = useRef(false);

  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen);
    if (!nextOpen) {
      setError(null);
    }
  }

  function handleConfirm() {
    if (submittingRef.current) {
      return;
    }
    submittingRef.current = true;
    setError(null);

    deleteNode.mutate(node.id, {
      onSuccess: () => {
        setOpen(false);
        onDeleted?.();
      },
      onError: (err) => setError(errorMessage(err)),
      onSettled: () => {
        submittingRef.current = false;
      },
    });
  }

  const hasContents = node.type === "folder" && (node.child_count ?? 0) > 0;

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete "{node.name}"?</DialogTitle>
          <DialogDescription>
            {hasContents
              ? "This folder isn't empty — deleting it removes everything inside. This can't be undone."
              : "This can't be undone."}
          </DialogDescription>
        </DialogHeader>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="secondary">
              Cancel
            </Button>
          </DialogClose>
          <Button type="button" variant="destructive" onClick={handleConfirm} loading={deleteNode.isPending}>
            Delete
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
