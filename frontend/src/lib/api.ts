import type {
  Order,
  RefundReason,
  RefundRequestDetail,
  RefundRequestSummary,
  RefundSubmission,
} from "./types";

const API_BASE_URL = (process.env.NEXT_PUBLIC_API_BASE_URL ?? "").replace(/\/$/, "");

type ApiFailure = {
  errors?: Record<string, string[]>;
};

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly fieldErrors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isUnauthorized(): boolean {
    return this.status === 401;
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}/api${path}`, {
      ...init,
      headers: { Accept: "application/json", "Content-Type": "application/json", ...init?.headers },
    });
  } catch {
    throw new ApiError("We could not reach the support service. Please try again.", 0);
  }

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const failure = payload as ApiFailure | null;
    const fieldErrors = failure?.errors ?? {};
    const message = response.status === 404
      ? "We could not find that record. Check the number and try again."
      : response.status === 422
        ? validationMessage(fieldErrors)
        : "The support service could not complete that request. Please try again.";

    throw new ApiError(message, response.status, fieldErrors);
  }

  return payload as T;
}

export function lookupOrder(orderNumber: string): Promise<Order> {
  return request<Order>(`/orders/${encodeURIComponent(orderNumber)}`);
}

export function submitRefundRequest(input: {
  order_id: number;
  requested_amount: string;
  reason: RefundReason;
  customer_message: string;
}): Promise<RefundSubmission> {
  return request<RefundSubmission>("/refund-requests", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function loginSupport(input: { username: string; password: string }): Promise<{ authenticated: true }> {
  return request<{ authenticated: true }>("/support/login", {
    method: "POST",
    credentials: "include",
    body: JSON.stringify(input),
  });
}

export function logoutSupport(): Promise<{ authenticated: false }> {
  return request<{ authenticated: false }>("/support/logout", {
    method: "POST",
    credentials: "include",
  });
}

export function getSupportSession(): Promise<{ authenticated: true }> {
  return request<{ authenticated: true }>("/support/session", { credentials: "include" });
}

export async function listRefundRequests(): Promise<RefundRequestSummary[]> {
  const response = await request<{ data: RefundRequestSummary[] }>("/refund-requests", { credentials: "include" });
  return response.data;
}

export function getRefundRequest(id: string): Promise<RefundRequestDetail> {
  return request<RefundRequestDetail>(`/refund-requests/${encodeURIComponent(id)}`, { credentials: "include" });
}

function validationMessage(errors: Record<string, string[]>): string {
  if (errors.customer_message) return "Please describe the issue in a short message.";
  if (errors.requested_amount) return "Enter a refund amount that does not exceed the order total.";
  if (errors.reason) return "Choose one of the listed issue types.";
  if (errors.order_id) return "Find a valid order before submitting your request.";

  return "Please check the information and try again.";
}
