"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError, listRefundRequests } from "@/lib/api";
import { formatCurrency, formatDateTime } from "@/lib/format";
import type { RefundOutcome, RefundRequestSummary } from "@/lib/types";
import { AppHeader } from "@/components/shared/AppHeader";
import { OutcomeBadge } from "@/components/shared/OutcomeBadge";
import styles from "./SupportDashboard.module.css";

type OutcomeFilter = "ALL" | RefundOutcome;

const filters: { label: string; value: OutcomeFilter }[] = [
  { label: "All", value: "ALL" },
  { label: "Approved", value: "APPROVED" },
  { label: "Denied", value: "DENIED" },
  { label: "Escalated", value: "ESCALATED" },
];

export function SupportDashboard() {
  const [requests, setRequests] = useState<RefundRequestSummary[]>([]);
  const [filter, setFilter] = useState<OutcomeFilter>("ALL");
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;

    listRefundRequests()
      .then((items) => {
        if (active) setRequests(items);
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(requestError instanceof ApiError
            ? requestError.message
            : "The request list is unavailable. Please try again.");
        }
      })
      .finally(() => {
        if (active) setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const filteredRequests = filter === "ALL"
    ? requests
    : requests.filter((request) => request.outcome === filter);
  const counts: Record<"ALL" | RefundOutcome, number> = {
    ALL: requests.length,
    APPROVED: requests.filter((request) => request.outcome === "APPROVED").length,
    DENIED: requests.filter((request) => request.outcome === "DENIED").length,
    ESCALATED: requests.filter((request) => request.outcome === "ESCALATED").length,
  };

  return (
    <div className={styles.page}>
      <AppHeader active="support" />
      <main className={styles.main}>
        <div className={styles.heading}>
          <div>
            <p className={styles.eyebrow}>WORKSPACE / REFUND OPERATIONS</p>
            <h1>Support dashboard</h1>
            <p className={styles.subheading}>Recent customer requests and their policy resolutions.</p>
          </div>
          <span className={styles.windowNote}>Showing up to 50 recent requests</span>
        </div>

        {error && <p className={styles.error} role="alert">{error}</p>}

        <section className={styles.summary} aria-label="Request summary">
          {filters.map((item) => (
            <div className={styles.summaryItem} key={item.value}>
              <span>{item.value === "ALL" ? "Total" : item.label}</span>
              <strong aria-label={`${item.value === "ALL" ? "Total" : item.label} count`}>
                {isLoading ? "—" : counts[item.value]}
              </strong>
            </div>
          ))}
        </section>

        <section className={styles.requestSection} aria-labelledby="recent-requests-heading">
          <div className={styles.listHeader}>
            <h2 id="recent-requests-heading">Recent requests</h2>
            <div className={styles.filters} aria-label="Filter by outcome">
              {filters.map((item) => (
                <button
                  aria-pressed={filter === item.value}
                  className={filter === item.value ? styles.selectedFilter : undefined}
                  key={item.value}
                  onClick={() => setFilter(item.value)}
                  type="button"
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>

          {isLoading ? (
            <p className={styles.state} role="status">Loading recent requests…</p>
          ) : !error && filteredRequests.length === 0 ? (
            <p className={styles.state}>
              {requests.length === 0 ? "No refund requests yet." : "No requests match this outcome."}
            </p>
          ) : !error ? (
            <div className={styles.tableWrap}>
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th scope="col">Request</th>
                    <th scope="col">Order / item</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Requested</th>
                    <th scope="col">Outcome</th>
                    <th scope="col">Submitted</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRequests.map((request) => <RequestRow key={request.id} request={request} />)}
                </tbody>
              </table>
            </div>
          ) : null}
        </section>
      </main>
    </div>
  );
}

function RequestRow({ request }: { request: RefundRequestSummary }) {
  return (
    <tr>
      <td data-label="Request">
        <Link className={styles.requestLink} href={`/support/refunds/${request.id}`}>
          #{request.id}
        </Link>
      </td>
      <td data-label="Order / item">
        <span className={styles.strongValue}>{request.order.order_number}</span>
        <span className={styles.secondaryValue}>{request.order.item_name}</span>
      </td>
      <td data-label="Customer">{request.customer.name}</td>
      <td data-label="Requested">{formatCurrency(request.requested_amount)}</td>
      <td data-label="Outcome">
        <OutcomeBadge outcome={request.outcome} />
        <span className={styles.aiStatus}>{formatAiStatus(request.ai_analysis_status)}</span>
      </td>
      <td data-label="Submitted">{formatDateTime(request.created_at)}</td>
    </tr>
  );
}

function formatAiStatus(status: RefundRequestSummary["ai_analysis_status"]): string {
  return status.toLowerCase().replaceAll("_", " ");
}
