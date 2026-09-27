import { render, screen, within } from "@testing-library/react";
import { ApiError, getRefundRequest } from "@/lib/api";
import type { RefundRequestDetail } from "@/lib/types";
import { RefundDetailView } from "./RefundDetailView";

jest.mock("@/lib/api", () => ({
  ApiError: class ApiError extends Error {
    constructor(message: string, public readonly status: number) {
      super(message);
    }
  },
  getRefundRequest: jest.fn(),
}));

const detail: RefundRequestDetail = {
  id: 28,
  requested_amount: "40.00",
  customer_reason_hint: "DAMAGED",
  customer_message: "The item arrived cracked.",
  created_at: "2026-09-26T12:00:00.000000Z",
  customer: { id: 1, name: "Avery Bennett" },
  order: {
    id: 1,
    order_number: "WN-1001",
    item_name: "Wireless Speaker",
    total_amount: "89.90",
    ordered_at: "2026-09-20T10:00:00.000000Z",
    final_sale: false,
  },
  ai: {
    status: "UNAVAILABLE",
    analysis: null,
    provider: "openai",
    model: "gpt-4o-mini",
    error_code: "PROVIDER_UNAVAILABLE",
  },
  policy: {
    outcome: "DENIED",
    reason_code: "UNSUPPORTED_REASON",
    explanation: "The message could not be interpreted into a supported reason.",
  },
  resolution: {
    outcome: "ESCALATED",
    reason_code: "AI_ANALYSIS_UNAVAILABLE",
    explanation: "Human review is required because automated analysis is unavailable.",
  },
};

const getRefundRequestMock = jest.mocked(getRefundRequest);

describe("RefundDetailView", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    getRefundRequestMock.mockResolvedValue(detail);
  });

  it("keeps AI analysis, policy evaluation, and final resolution visibly distinct", async () => {
    render(<RefundDetailView requestId="28" />);

    const aiSection = await screen.findByRole("region", { name: /ai analysis/i });
    const policySection = screen.getByRole("region", { name: /policy evaluation/i });
    const resolutionSection = screen.getByRole("region", { name: /final resolution/i });

    expect(within(aiSection).getByText("unavailable")).toBeInTheDocument();
    expect(within(policySection).getByText("DENIED")).toBeInTheDocument();
    expect(within(policySection).getByText("UNSUPPORTED_REASON")).toBeInTheDocument();
    expect(within(resolutionSection).getByText("ESCALATED")).toBeInTheDocument();
    expect(within(resolutionSection).getByText("AI_ANALYSIS_UNAVAILABLE")).toBeInTheDocument();
    expect(within(resolutionSection).getByText(
      "Because AI analysis did not complete, the final resolution escalates this request for human review. The policy evaluation shown above remains the deterministic fallback result.",
    )).toBeInTheDocument();
    expect(screen.getByText("Avery Bennett")).toBeInTheDocument();
    expect(screen.getByText("Sep 26, 2026, 12:00 PM UTC")).toBeInTheDocument();
    expect(screen.queryByText(/@/)).not.toBeInTheDocument();
  });

  it("honestly labels a seeded record that was not analyzed", async () => {
    getRefundRequestMock.mockResolvedValue({
      ...detail,
      ai: { status: "NOT_ANALYZED", analysis: null, provider: null, model: null, error_code: null },
    });
    render(<RefundDetailView requestId="28" />);

    expect(await screen.findByText(/seeded demonstration record was evaluated by deterministic policy only/i)).toBeInTheDocument();
  });

  it("associates AI analysis terms with their definitions", async () => {
    getRefundRequestMock.mockResolvedValue({
      ...detail,
      ai: {
        ...detail.ai,
        status: "ANALYZED",
        analysis: {
          classified_reason: "DAMAGED",
          summary: "The item arrived cracked.",
          confidence: 0.94,
          suspicious: false,
          conflicting_claims: false,
          suggested_response: "We are sorry the item arrived damaged.",
        },
      },
    });
    render(<RefundDetailView requestId="28" />);

    const aiSection = await screen.findByRole("region", { name: /ai analysis/i });
    const terms = within(aiSection).getAllByRole("term");

    for (const label of ["Classified reason", "Confidence", "Suspicious", "Conflicting claims"]) {
      const term = terms.find((item) => item.textContent === label);
      expect(term).toBeDefined();
      expect(term?.closest("dl")).not.toBeNull();
    }
  });

  it("renders a not-found state for an unknown refund request", async () => {
    getRefundRequestMock.mockRejectedValue(new ApiError("We could not find that record.", 404));
    render(<RefundDetailView requestId="999" />);

    expect(await screen.findByRole("heading", { name: "Request not found" })).toBeInTheDocument();
  });

  it("renders an unavailable state when the detail API fails", async () => {
    getRefundRequestMock.mockRejectedValue(new ApiError("The service is unavailable.", 503));
    render(<RefundDetailView requestId="28" />);

    expect(await screen.findByRole("heading", { name: "Request unavailable" })).toBeInTheDocument();
    expect(screen.getByRole("alert")).toHaveTextContent("The service is unavailable.");
  });
});
