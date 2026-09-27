import { fireEvent, render, screen, within } from "@testing-library/react";
import { listRefundRequests } from "@/lib/api";
import type { RefundRequestSummary } from "@/lib/types";
import { SupportDashboard } from "./SupportDashboard";

jest.mock("@/lib/api", () => ({
  ApiError: class ApiError extends Error {},
  listRefundRequests: jest.fn(),
}));

const requests: RefundRequestSummary[] = [
  {
    id: 31,
    requested_amount: "40.00",
    outcome: "ESCALATED",
    reason_code: "AI_ANALYSIS_UNAVAILABLE",
    ai_analysis_status: "UNAVAILABLE",
    created_at: "2026-09-26T12:00:00.000000Z",
    customer: { id: 1, name: "Avery Bennett" },
    order: { id: 1, order_number: "WN-1001", item_name: "Wireless Speaker" },
  },
  {
    id: 30,
    requested_amount: "25.00",
    outcome: "APPROVED",
    reason_code: "ELIGIBLE_DAMAGED_ITEM",
    ai_analysis_status: "ANALYZED",
    created_at: "2026-09-26T11:00:00.000000Z",
    customer: { id: 2, name: "Jordan Brooks" },
    order: { id: 2, order_number: "WN-1002", item_name: "Desk Lamp" },
  },
];

const listRefundRequestsMock = jest.mocked(listRefundRequests);

describe("SupportDashboard", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    listRefundRequestsMock.mockResolvedValue(requests);
  });

  it("shows live request totals and filters recent requests by outcome", async () => {
    render(<SupportDashboard />);

    expect(await screen.findByText("Avery Bennett")).toBeInTheDocument();
    expect(screen.getByText("Total")).toBeInTheDocument();
    expect(screen.getByText("Sep 26, 2026, 12:00 PM UTC")).toBeInTheDocument();
    const summary = screen.getByRole("region", { name: /request summary/i });
    expect(within(summary).getByLabelText("Total count")).toHaveTextContent("2");
    expect(within(summary).getByLabelText("Approved count")).toHaveTextContent("1");
    expect(within(summary).getByLabelText("Escalated count")).toHaveTextContent("1");
    expect(screen.getByText("WN-1001")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Approved" }));

    expect(screen.queryByText("WN-1001")).not.toBeInTheDocument();
    expect(screen.getByText("WN-1002")).toBeInTheDocument();
    expect(screen.getByText("Jordan Brooks")).toBeInTheDocument();
  });

  it("renders a deliberate empty state when no requests exist", async () => {
    listRefundRequestsMock.mockResolvedValue([]);
    render(<SupportDashboard />);

    expect(await screen.findByText("Showing up to 50 recent requests")).toBeInTheDocument();
    expect(await screen.findByText(/no refund requests yet/i)).toBeInTheDocument();
  });

  it("renders a deliberate error state when the request list is unavailable", async () => {
    listRefundRequestsMock.mockRejectedValue(new Error("network unavailable"));
    render(<SupportDashboard />);

    expect(await screen.findByRole("alert")).toHaveTextContent(/request list is unavailable/i);
  });
});
