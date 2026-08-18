const BASE_URL = "/api/v1";

/**
 * Normalizes both API error shapes (ADR-0004 part 2: RFC 9457 problem+json
 * for domain errors, Laravel's native {message, errors} for field validation)
 * into one type so callers branch on `code`, never on response shape.
 */
export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fieldErrors?: Record<string, string[]>;

  constructor(message: string, status: number, code: string, fieldErrors?: Record<string, string[]>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.fieldErrors = fieldErrors;
  }
}

interface ProblemDetailsBody {
  detail?: string;
  title?: string;
  code?: string;
}

interface ValidationErrorBody {
  message?: string;
  errors: Record<string, string[]>;
}

async function parseErrorResponse(response: Response): Promise<ApiError> {
  const contentType = response.headers.get("content-type") ?? "";
  const body: unknown = await response.json().catch(() => null);

  if (contentType.includes("application/problem+json")) {
    const problem = body as ProblemDetailsBody | null;
    return new ApiError(
      problem?.detail ?? problem?.title ?? response.statusText,
      response.status,
      problem?.code ?? "UNKNOWN_ERROR",
    );
  }

  if (body && typeof body === "object" && "errors" in body) {
    const validation = body as ValidationErrorBody;
    return new ApiError(
      validation.message ?? "The given data was invalid.",
      response.status,
      "VALIDATION_ERROR",
      validation.errors,
    );
  }

  return new ApiError(response.statusText || "Request failed", response.status, "UNKNOWN_ERROR");
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
  });

  if (!response.ok) {
    throw await parseErrorResponse(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export const client = {
  get: <T>(path: string): Promise<T> => request<T>(path),
  post: <T>(path: string, body: unknown): Promise<T> =>
    request<T>(path, { method: "POST", body: JSON.stringify(body) }),
  delete: (path: string): Promise<void> => request<void>(path, { method: "DELETE" }),
};
