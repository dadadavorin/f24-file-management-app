import { type FormEvent, type ReactNode, useId, useRef, useState } from "react";
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
import { Input } from "../../../components/ui/Input";
import { useCreateNode } from "../hooks/useCreateNode";
import type { NodeType } from "../types";

export interface CreateNodeDialogProps {
  parentId: number;
  type: NodeType;
  trigger: ReactNode;
}

function errorMessage(error: unknown, type: NodeType, name: string): string {
  if (isApiError(error, "DUPLICATE_NODE_NAME")) {
    return `A ${type} named "${name}" already exists here.`;
  }
  if (isApiError(error, "NODE_NOT_FOUND")) {
    return "That folder no longer exists.";
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Something went wrong. Try again.";
}

export function CreateNodeDialog({ parentId, type, trigger }: CreateNodeDialogProps) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [fieldError, setFieldError] = useState<string | null>(null);
  const createNode = useCreateNode();
  const nameInputId = useId();
  // Guards double submission independently of `isPending`'s render timing —
  // two rapid clicks can both fire before React re-renders the disabled button.
  const submittingRef = useRef(false);

  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen);
    if (!nextOpen) {
      setName("");
      setFieldError(null);
    }
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (submittingRef.current) {
      return;
    }
    submittingRef.current = true;
    setFieldError(null);

    createNode.mutate(
      { parent_id: parentId, type, name },
      {
        onSuccess: () => handleOpenChange(false),
        onError: (error) => {
          if (isApiError(error, "VALIDATION_ERROR") && error.fieldErrors?.name) {
            setFieldError(error.fieldErrors.name[0]);
          } else {
            setFieldError(errorMessage(error, type, name));
          }
        },
        onSettled: () => {
          submittingRef.current = false;
        },
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New {type}</DialogTitle>
          <DialogDescription>Choose a name for the new {type}.</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <label htmlFor={nameInputId} className="sr-only">
            Name
          </label>
          <Input
            id={nameInputId}
            autoFocus
            value={name}
            onChange={(event) => setName(event.target.value)}
            invalid={Boolean(fieldError)}
            placeholder={type === "folder" ? "Folder name" : "File name"}
          />
          {fieldError && <p className="mt-2 text-sm text-destructive">{fieldError}</p>}
          <DialogFooter>
            <DialogClose asChild>
              <Button type="button" variant="secondary">
                Cancel
              </Button>
            </DialogClose>
            <Button type="submit" loading={createNode.isPending}>
              Create
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
