import { act, fireEvent, render, screen } from "@testing-library/react";
import {
  ApiError,
  lookupOrder,
  submitRefundRequest,
} from "@/lib/api";
import type { Order, RefundSubmission } from "@/lib/types";
import { CustomerRefundFlow } from "./CustomerRefundFlow";

jest.mock("@/lib/api", () => ({
  ApiError: jest.fn().mockImplementation((message: string) => new Error(message)),
  lookupOrder: jest.fn(),
  submitRefundRequest: jest.fn(),
}));

const order: Order = {
  id: 1,
  order_number: "WN-1001",
  item_name: "Wireless Speaker",
  total_amount: "89.90",
  ordered_at: "2026-09-20T10:00:00.000000Z",
  final_sale: false,
};

const approvedResult: RefundSubmission = {
  id: 12,
  customer_id: 1,
  order_id: 1,
  outcome: "APPROVED",
  reason_code: "ELIGIBLE_DAMAGED_ITEM",
  explanation: "The damaged item request is within the refund window and is eligible for a refund.",
  ai_analysis_status: "ANALYZED",
  ai_analysis: {
    classified_reason: "DAMAGED",
    summary: "The speaker arrived with a cracked case.",
    suspicious: false,
    conflicting_claims: false,
    confidence: 0.94,
    suggested_response: "We are sorry the speaker arrived damaged.",
  },
  ai_provider: "openai",
  ai_model: "gpt-4o-mini",
  policy: {
    outcome: "APPROVED",
    reason_code: "ELIGIBLE_DAMAGED_ITEM",
    explanation: "The damaged item request is within the refund window and is eligible for a refund.",
  },
};

const unavailableResult: RefundSubmission = {
  ...approvedResult,
  outcome: "ESCALATED",
  reason_code: "AI_ANALYSIS_UNAVAILABLE",
  explanation: "Your request needs review because automated analysis is temporarily unavailable.",
  ai_analysis_status: "UNAVAILABLE",
  ai_analysis: null,
  policy: {
    outcome: "DENIED",
    reason_code: "UNSUPPORTED_REASON",
    explanation: "The request reason is unsupported.",
  },
};

const lookupOrderMock = jest.mocked(lookupOrder);
const submitRefundRequestMock = jest.mocked(submitRefundRequest);

describe("CustomerRefundFlow", () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it("looks up an order and displays its facts before the refund form", async () => {
    lookupOrderMock.mockResolvedValue(order);
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1001" },
    });
    fireEvent.click(screen.getByRole("button", { name: /find order/i }));

    expect(await screen.findByRole("heading", { name: "Wireless Speaker" })).toBeInTheDocument();
    expect(screen.getByText("WN-1001")).toBeInTheDocument();
    expect(screen.getByText("$89.90")).toBeInTheDocument();
    expect(screen.getByText("Final sale: No")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: /tell us what happened/i })).toBeInTheDocument();
  });

  it("shows the final approval and advisory AI interpretation after submission", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockResolvedValue(approvedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("heading", { name: "APPROVED" })).toBeInTheDocument();
    expect(screen.getByText(approvedResult.explanation)).toBeInTheDocument();
    expect(screen.getByText("The speaker arrived with a cracked case.")).toBeInTheDocument();
    expect(screen.getByText(/final decision is determined by refund policy/i)).toBeInTheDocument();
  });

  it("shows human-review guidance for AI-unavailable escalation", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockResolvedValue(unavailableResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("heading", { name: "ESCALATED" })).toBeInTheDocument();
    expect(screen.getByText("A support specialist will review your request because automated analysis is temporarily unavailable.")).toBeInTheDocument();
  });

  it("displays a useful validation message returned by the API", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockRejectedValue(new ApiError("Please describe the issue in a short message.", 422));
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("alert")).toHaveTextContent(/please describe the issue/i);
  });
});

async function findOrder(): Promise<void> {
  fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
    target: { value: "WN-1001" },
  });
  fireEvent.click(screen.getByRole("button", { name: /find order/i }));
  await screen.findByRole("heading", { name: "Wireless Speaker" });
}

async function submitMessage(message: string): Promise<void> {
  fireEvent.change(screen.getByRole("combobox", { name: /what best describes the issue/i }), {
    target: { value: "DAMAGED" },
  });
  fireEvent.change(screen.getByRole("textbox", { name: /tell us what happened/i }), {
    target: { value: message },
  });
  await act(async () => {
    fireEvent.click(screen.getByRole("button", { name: /submit refund request/i }));
  });
}
