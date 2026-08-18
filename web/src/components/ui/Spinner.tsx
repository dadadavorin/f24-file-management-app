import { cn } from "../../lib/cn";

const SIZE_CLASSES = {
  sm: "h-4 w-4 border-2",
  md: "h-6 w-6 border-2",
  lg: "h-10 w-10 border-[3px]",
} as const;

export interface SpinnerProps {
  size?: keyof typeof SIZE_CLASSES;
  className?: string;
  label?: string;
}

export function Spinner({ size = "md", className, label = "Loading" }: SpinnerProps) {
  return (
    <span
      role="status"
      aria-label={label}
      className={cn(
        "inline-block animate-spin rounded-full border-current border-t-transparent text-muted-foreground",
        SIZE_CLASSES[size],
        className,
      )}
    />
  );
}
