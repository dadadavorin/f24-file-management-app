import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, client } from "./client";

function jsonResponse(status: number, contentType: string, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "content-type": contentType },
  });
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("client", () => {
  it("resolves with the parsed body on success", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(jsonResponse(200, "application/json", { data: { id: 1 } })),
    );

    await expect(client.get<{ data: { id: number } }>("/nodes/root")).resolves.toEqual({
      data: { id: 1 },
    });
  });

  it("parses a problem+json response into an ApiError carrying its code", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse(409, "application/problem+json", {
          type: "https://f24-file-management-app.example/problems/duplicate-node-name",
          title: "Duplicate name",
          status: 409,
          detail: 'A node named "Invoices" already exists in this folder.',
          code: "DUPLICATE_NODE_NAME",
        }),
      ),
    );

    const error = await client.post("/nodes", { parent_id: 1, type: "folder", name: "Invoices" }).catch((e) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({
      status: 409,
      code: "DUPLICATE_NODE_NAME",
      message: 'A node named "Invoices" already exists in this folder.',
    });
  });

  it("parses the native validation-error shape into an ApiError with fieldErrors", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse(422, "application/json", {
          message: "The given data was invalid.",
          errors: { name: ["A name cannot be blank."] },
        }),
      ),
    );

    const error = (await client.post("/nodes", { parent_id: 1, type: "folder", name: "" }).catch((e) => e)) as ApiError;

    expect(error).toBeInstanceOf(ApiError);
    expect(error.code).toBe("VALIDATION_ERROR");
    expect(error.status).toBe(422);
    expect(error.fieldErrors).toEqual({ name: ["A name cannot be blank."] });
  });

  it("resolves DELETE requests to undefined on 204 No Content", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 204 })));

    await expect(client.delete("/nodes/7")).resolves.toBeUndefined();
  });
});
