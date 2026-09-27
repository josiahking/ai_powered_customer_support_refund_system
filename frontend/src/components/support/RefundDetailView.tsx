"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError, getRefundRequest } from "@/lib/api";
import { formatCurrency, formatDate, formatDateTime } from "@/lib/format";
import type { RefundRequestDetail as RefundRequestDetailData } from "@/lib/types";
import { AppHeader } from "@/components/shared/AppHeader";
import { OutcomeBadge } from "@/components/shared/OutcomeBadge";
import styles from "./RefundDetailView.module.css";

type RefundDetailViewProps = {
  requestId: string;
};

export function RefundDetailView({ requestId }: RefundDetailViewProps) {
  const [request, setRequest] = useState<RefundRequestDetailData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    let active = true;

    getRefundRequest(requestId)
      .then((data) => {
        if (active) setRequest(data);
      })
      .catch((requestError: unknown) => {
        if (active) {
          setNotFound(requestError instanceof ApiError && requestError.status === 404);
          setError(requestError instanceof Error
            ? requestError.message
            : "We could not load this refund request. Please try again.");
        }
      })
      .finally(() => {
        if (active) setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [requestId]);

  return (
    <div className={styles.page}>
      <AppHeader active="support" />
      <main className={styles.main}>
        <Link className={styles.backLink} href="/support">Back to support dashboard</Link>
        {isLoading ? (
          <p className={styles.state} role="status">Loading refund detail…</p>
        ) : error ? (
          <section className={styles.errorState} role="alert">
            <h1>{notFound ? "Request not found" : "Request unavailable"}</h1>
            <p>{error}</p>
          </section>
        ) : request ? (
          <RefundDetail request={request} />
        ) : null}
      </main>
    </div>
  );
}

function RefundDetail({ request }: { request: RefundRequestDetailData }) {
  const analysis = request.ai.analysis;
  const hasAiFailureResolution = request.ai.status !== "ANALYZED"
    && request.ai.status !== "NOT_ANALYZED"
    && request.resolution.reason_code.startsWith("AI_ANALYSIS_");

  return (
    <>
      <header className={styles.heading}>
        <div>
          <p className={styles.eyebrow}>SUPPORT / REQUEST #{request.id}</p>
          <h1>{request.order.order_number}</h1>
          <p className={styles.itemName}>{request.order.item_name}</p>
        </div>
        <OutcomeBadge outcome={request.resolution.outcome} />
      </header>

      <div className={styles.auditGrid}>
        <section className={styles.auditSection} aria-label="Customer request">
          <p className={styles.sectionIndex}>01 / CUSTOMER</p>
          <h2>Customer request</h2>
          <dl className={styles.facts}>
            <Fact label="Customer" value={request.customer.name} />
            <Fact label="Order" value={request.order.order_number} />
            <Fact label="Item" value={request.order.item_name} />
            <Fact label="Requested" value={formatCurrency(request.requested_amount)} />
            <Fact label="Order total" value={formatCurrency(request.order.total_amount)} />
            <Fact label="Placed" value={formatDate(request.order.ordered_at)} />
            <Fact label="Reason hint" value={request.customer_reason_hint.replaceAll("_", " ")} />
            <Fact label="Received" value={formatDateTime(request.created_at)} />
          </dl>
          {request.order.final_sale && <p className={styles.finalSale}>Order marked final sale</p>}
          <blockquote className={styles.message}>{request.customer_message}</blockquote>
        </section>

        <section className={styles.auditSection} aria-label="AI analysis">
          <p className={styles.sectionIndex}>02 / MESSAGE INTERPRETATION</p>
          <h2>AI analysis</h2>
          <p className={styles.statusLine}>Analysis status <strong>{formatStatus(request.ai.status)}</strong></p>
          {analysis ? (
            <>
              <dl>
                <Fact label="Classified reason" value={analysis.classified_reason.replaceAll("_", " ")} />
              </dl>
              <p className={styles.summaryText}>{analysis.summary}</p>
              <dl className={styles.signalRow}>
                <Fact label="Confidence" value={`${Math.round(analysis.confidence * 100)}%`} />
                <Fact label="Suspicious" value={analysis.suspicious ? "Yes" : "No"} />
                <Fact label="Conflicting claims" value={analysis.conflicting_claims ? "Yes" : "No"} />
              </dl>
              <div className={styles.suggestion}>
                <span>Suggested response</span>
                <p>{analysis.suggested_response}</p>
              </div>
            </>
          ) : request.ai.status === "NOT_ANALYZED" ? (
            <p className={styles.notice}>This seeded demonstration record was evaluated by deterministic policy only.</p>
          ) : (
            <p className={styles.notice}>Automated analysis did not complete. No AI classification or suggestion is available.</p>
          )}
          <dl className={styles.providerFacts}>
            <Fact label="Provider" value={request.ai.provider ?? "Not used"} />
            <Fact label="Model" value={request.ai.model ?? "Not used"} />
            {request.ai.error_code && <Fact label="Failure category" value={request.ai.error_code} />}
          </dl>
        </section>

        <AuditDecision
          className={styles.policySection}
          label="03 / DETERMINISTIC RULES"
          title="Policy evaluation"
          outcome={request.policy.outcome}
          reason={request.policy.reason_code}
          explanation={request.policy.explanation}
          ariaLabel="Policy evaluation"
        />

        <AuditDecision
          className={styles.resolutionSection}
          label="04 / FINAL RESULT"
          title="Final resolution"
          outcome={request.resolution.outcome}
          reason={request.resolution.reason_code}
          explanation={request.resolution.explanation}
          ariaLabel="Final resolution"
          fallbackNote={hasAiFailureResolution
            ? "Because AI analysis did not complete, the final resolution escalates this request for human review. The policy evaluation shown above remains the deterministic fallback result."
            : undefined}
        />
      </div>
    </>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className={styles.fact}>
      <dt>{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}

function AuditDecision({
  className,
  label,
  title,
  outcome,
  reason,
  explanation,
  ariaLabel,
  fallbackNote,
}: {
  className: string;
  label: string;
  title: string;
  outcome: RefundRequestDetailData["policy"]["outcome"];
  reason: string;
  explanation: string;
  ariaLabel: string;
  fallbackNote?: string;
}) {
  return (
    <section className={`${styles.auditSection} ${className}`} aria-label={ariaLabel}>
      <p className={styles.sectionIndex}>{label}</p>
      <div className={styles.decisionHeading}>
        <h2>{title}</h2>
        <OutcomeBadge outcome={outcome} />
      </div>
      <p className={styles.reasonCode}>{reason}</p>
      <p className={styles.explanation}>{explanation}</p>
      {fallbackNote && <p className={styles.fallbackNote}>{fallbackNote}</p>}
    </section>
  );
}

function formatStatus(status: string): string {
  return status.toLowerCase().replaceAll("_", " ");
}
