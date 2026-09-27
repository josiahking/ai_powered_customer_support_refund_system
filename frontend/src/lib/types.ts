export type RefundReason = "DAMAGED" | "INCORRECT_ITEM" | "OTHER";
export type RefundOutcome = "APPROVED" | "DENIED" | "ESCALATED";
export type AiAnalysisStatus = "ANALYZED" | "NOT_ANALYZED" | "UNAVAILABLE" | "RATE_LIMITED" | "INVALID_RESPONSE";

export type Order = {
  id: number;
  order_number: string;
  item_name: string;
  total_amount: string;
  ordered_at: string;
  final_sale: boolean;
};

export type AiAnalysis = {
  classified_reason: RefundReason;
  summary: string;
  suspicious: boolean;
  conflicting_claims: boolean;
  confidence: number;
  suggested_response: string;
};

export type PolicyResult = {
  outcome: RefundOutcome;
  reason_code: string;
  explanation: string;
};

export type RefundSubmission = {
  id: number;
  customer_id: number;
  order_id: number;
  outcome: RefundOutcome;
  reason_code: string;
  explanation: string;
  ai_analysis_status: AiAnalysisStatus;
  ai_analysis: AiAnalysis | null;
  ai_provider: string | null;
  ai_model: string | null;
  policy: PolicyResult;
};

export type RefundRequestSummary = {
  id: number;
  requested_amount: string;
  outcome: RefundOutcome;
  reason_code: string;
  ai_analysis_status: AiAnalysisStatus;
  created_at: string;
  customer: { id: number; name: string };
  order: { id: number; order_number: string; item_name: string };
};

export type RefundRequestDetail = {
  id: number;
  requested_amount: string;
  customer_reason_hint: RefundReason;
  customer_message: string;
  created_at: string;
  customer: { id: number; name: string };
  order: Order;
  ai: {
    status: AiAnalysisStatus;
    analysis: AiAnalysis | null;
    provider: string | null;
    model: string | null;
    error_code: string | null;
  };
  policy: PolicyResult;
  resolution: PolicyResult;
};