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

const finalSaleOrder: Order = {
  ...order,
  order_number: "WN-1008",
  item_name: "Clearance Headphones",
  final_sale: true,
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

const deniedResult: RefundSubmission = {
  ...approvedResult,
  outcome: "DENIED",
  reason_code: "FINAL_SALE",
  explanation: "This order was marked final sale and is not eligible for a refund.",
  policy: {
    outcome: "DENIED",
    reason_code: "FINAL_SALE",
    explanation: "This order was marked final sale and is not eligible for a refund.",
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
    expect(screen.queryByText(/final sale/i)).not.toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: /tell us what happened/i })).toBeInTheDocument();
  });

  it("warns customers when an order is final sale", async () => {
    lookupOrderMock.mockResolvedValue(finalSaleOrder);
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1008" },
    });
    fireEvent.click(screen.getByRole("button", { name: /find order/i }));

    expect(await screen.findByRole("heading", { name: "Clearance Headphones" })).toBeInTheDocument();
    expect(screen.getByText("Final sale — this item is not refundable.")).toBeInTheDocument();
  });

  it("shows the final approval without exposing AI analysis after submission", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockResolvedValue(approvedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("heading", { name: "APPROVED" })).toBeInTheDocument();
    expect(screen.getByText(approvedResult.explanation)).toBeInTheDocument();
    expect(screen.queryByText("AI interpretation")).not.toBeInTheDocument();
    expect(screen.queryByText("MESSAGE ANALYSIS")).not.toBeInTheDocument();
    expect(screen.queryByText("The speaker arrived with a cracked case.")).not.toBeInTheDocument();
    expect(screen.queryByText(/Issue identified|Suggested response/i)).not.toBeInTheDocument();
    expect(screen.queryByText("We are sorry the speaker arrived damaged.")).not.toBeInTheDocument();
    expect(screen.queryByText(/ANALYZED|provider|model|classification|confidence|suspicious|conflicting/i)).not.toBeInTheDocument();
    expect(screen.getByText(/final decision is determined by refund policy/i)).toBeInTheDocument();
  });

  it("shows a customer-safe escalation without AI operational messaging", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockResolvedValue(unavailableResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("heading", { name: "ESCALATED" })).toBeInTheDocument();
    expect(screen.getByText("Your request needs a closer look before we can make a decision.")).toBeInTheDocument();
    expect(screen.getByText("A support specialist will review your request.")).toBeInTheDocument();
    expect(screen.queryByText(/AI interpretation/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/MESSAGE ANALYSIS/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Automated analysis is temporarily unavailable/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/The analysis supports the review/i)).not.toBeInTheDocument();
  });

  it("shows a useful error when an order number is unknown", async () => {
    lookupOrderMock.mockRejectedValue(new ApiError("We could not find that record. Check the number and try again.", 404));
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-UNKNOWN" },
    });
    fireEvent.click(screen.getByRole("button", { name: /find order/i }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/could not find/i);
  });

  it("shows useful guidance when order lookup cannot reach the backend", async () => {
    lookupOrderMock.mockRejectedValue(new ApiError("We could not reach the support service. Please try again.", 0));
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1001" },
    });
    fireEvent.click(screen.getByRole("button", { name: /find order/i }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/could not reach the support service/i);
  });

  it("renders a denied outcome with its policy explanation", async () => {
    lookupOrderMock.mockResolvedValue(order);
    submitRefundRequestMock.mockResolvedValue(deniedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The item is damaged.");

    expect(await screen.findByRole("heading", { name: "DENIED" })).toBeInTheDocument();
    expect(screen.getByText(deniedResult.explanation)).toBeInTheDocument();
  });

  it("does not submit twice while a refund request is still processing", async () => {
    lookupOrderMock.mockResolvedValue(order);
    let resolveSubmission!: (value: RefundSubmission) => void;
    submitRefundRequestMock.mockImplementation(() => new Promise((resolve) => {
      resolveSubmission = resolve;
    }));
    render(<CustomerRefundFlow />);
    await findOrder();

    fireEvent.change(screen.getByRole("combobox", { name: /what best describes the issue/i }), {
      target: { value: "DAMAGED" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: /tell us what happened/i }), {
      target: { value: "The speaker arrived cracked." },
    });
    const submitButton = screen.getByRole("button", { name: /submit refund request/i });
    fireEvent.click(submitButton);
    fireEvent.click(submitButton);

    expect(submitRefundRequestMock).toHaveBeenCalledTimes(1);
    expect(submitButton).toBeDisabled();
    await act(async () => resolveSubmission(approvedResult));
    expect(await screen.findByRole("heading", { name: "APPROVED" })).toBeInTheDocument();
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
