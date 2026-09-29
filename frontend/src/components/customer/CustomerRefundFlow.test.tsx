import { act, fireEvent, render, screen } from "@testing-library/react";
import {
  ApiError,
  verifyOrder,
  submitRefundRequest,
} from "@/lib/api";
import type { CustomerOrder, RefundSubmission } from "@/lib/types";
import { CustomerRefundFlow } from "./CustomerRefundFlow";

jest.mock("@/lib/api", () => ({
  ApiError: jest.fn().mockImplementation((message: string) => new Error(message)),
  verifyOrder: jest.fn(),
  submitRefundRequest: jest.fn(),
}));

const order: CustomerOrder = {
  order_number: "WN-1001",
  item_name: "Wireless Speaker",
  total_amount: "89.90",
  ordered_at: "2026-09-20T10:00:00.000000Z",
  final_sale: false,
};

const finalSaleOrder: CustomerOrder = {
  ...order,
  order_number: "WN-1008",
  item_name: "Clearance Headphones",
  final_sale: true,
};

const approvedResult: RefundSubmission = {
  id: 12,
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

const verifiedOrder = { order, order_access_token: "opaque-test-capability", expires_in_minutes: 15 };
const finalSaleVerifiedOrder = { ...verifiedOrder, order: finalSaleOrder };
const verifyOrderMock = jest.mocked(verifyOrder);
const submitRefundRequestMock = jest.mocked(submitRefundRequest);

describe("CustomerRefundFlow", () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it("looks up an order and displays its facts before the refund form", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1001" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: /email used for this order/i }), {
      target: { value: "avery.bennett@example.test" },
    });
    fireEvent.click(screen.getByRole("button", { name: /verify order/i }));

    expect(await screen.findByRole("heading", { name: "Wireless Speaker" })).toBeInTheDocument();
    expect(screen.getByText("WN-1001")).toBeInTheDocument();
    expect(screen.getByText("$89.90")).toBeInTheDocument();
    expect(screen.queryByText(/final sale/i)).not.toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: /tell us what happened/i })).toBeInTheDocument();
    expect(verifyOrderMock).toHaveBeenCalledWith("WN-1001", "avery.bennett@example.test");
  });

  it("warns customers when an order is final sale", async () => {
    verifyOrderMock.mockResolvedValue(finalSaleVerifiedOrder);
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1008" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: /email used for this order/i }), {
      target: { value: "jamie.hall@example.test" },
    });
    fireEvent.click(screen.getByRole("button", { name: /verify order/i }));

    expect(await screen.findByRole("heading", { name: "Clearance Headphones" })).toBeInTheDocument();
    expect(screen.getByText("Final sale — this item is not refundable.")).toBeInTheDocument();
  });

  it("shows the final approval without exposing AI analysis after submission", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
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
    expect(submitRefundRequestMock).toHaveBeenCalledWith(expect.objectContaining({
      order_access_token: "opaque-test-capability",
    }));
    expect(submitRefundRequestMock.mock.calls[0][0]).not.toHaveProperty("order_id");
  });

  it("shows a customer-safe escalation without AI operational messaging", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
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
    verifyOrderMock.mockRejectedValue(new ApiError("We could not verify that order. Check the order number and email and try again.", 404));
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-UNKNOWN" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: /email used for this order/i }), {
      target: { value: "wrong.customer@example.test" },
    });
    fireEvent.click(screen.getByRole("button", { name: /verify order/i }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/could not verify that order/i);
  });

  it("shows useful guidance when order lookup cannot reach the backend", async () => {
    verifyOrderMock.mockRejectedValue(new ApiError("We could not reach the support service. Please try again.", 0));
    render(<CustomerRefundFlow />);

    fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
      target: { value: "WN-1001" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: /email used for this order/i }), {
      target: { value: "avery.bennett@example.test" },
    });
    fireEvent.click(screen.getByRole("button", { name: /verify order/i }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/could not reach the support service/i);
  });

  it("renders a denied outcome with its policy explanation", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
    submitRefundRequestMock.mockResolvedValue(deniedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The item is damaged.");

    expect(await screen.findByRole("heading", { name: "DENIED" })).toBeInTheDocument();
    expect(screen.getByText(deniedResult.explanation)).toBeInTheDocument();
  });

  it("does not submit twice while a refund request is still processing", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
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
    verifyOrderMock.mockResolvedValue(verifiedOrder);
    submitRefundRequestMock.mockRejectedValue(new ApiError("Please describe the issue in a short message.", 422));
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("alert")).toHaveTextContent(/please describe the issue/i);
  });

  it("returns to order verification when the access token has expired", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
    submitRefundRequestMock.mockRejectedValue(Object.assign(
      new Error("Your order verification has expired. Verify the order again before submitting."),
      { status: 403 },
    ));
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("alert")).toHaveTextContent(/verification has expired/i);
    expect(screen.getByRole("textbox", { name: /order number/i })).toBeInTheDocument();
    expect(screen.queryByRole("region", { name: "Order found" })).not.toBeInTheDocument();
  });

  it("requires verification again after starting another request", async () => {
    verifyOrderMock.mockResolvedValue(verifiedOrder);
    submitRefundRequestMock.mockResolvedValue(approvedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    await submitMessage("The speaker arrived cracked.");
    await screen.findByRole("heading", { name: "APPROVED" });

    fireEvent.click(screen.getByRole("button", { name: /start another request/i }));
    expect(screen.getByRole("textbox", { name: /order number/i })).toHaveValue("");
    expect(screen.queryByRole("region", { name: "Order found" })).not.toBeInTheDocument();
    expect(verifyOrderMock).toHaveBeenCalledTimes(1);
  });

  it("uses a fresh capability after changing the verified order", async () => {
    verifyOrderMock.mockResolvedValueOnce(verifiedOrder).mockResolvedValueOnce({
      ...verifiedOrder,
      order_access_token: "second-order-capability",
    });
    submitRefundRequestMock.mockResolvedValue(approvedResult);
    render(<CustomerRefundFlow />);
    await findOrder();
    fireEvent.click(screen.getByRole("button", { name: /change order/i }));
    expect(screen.queryByRole("region", { name: "Order found" })).not.toBeInTheDocument();

    await findOrder();
    await submitMessage("The speaker arrived cracked.");

    expect(await screen.findByRole("heading", { name: "APPROVED" })).toBeInTheDocument();
    expect(submitRefundRequestMock).toHaveBeenCalledWith(expect.objectContaining({
      order_access_token: "second-order-capability",
    }));
  });
});

async function findOrder(): Promise<void> {
  fireEvent.change(screen.getByRole("textbox", { name: /order number/i }), {
    target: { value: "WN-1001" },
  });
  fireEvent.change(screen.getByRole("textbox", { name: /email used for this order/i }), {
    target: { value: "avery.bennett@example.test" },
  });
  fireEvent.click(screen.getByRole("button", { name: /verify order/i }));
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
