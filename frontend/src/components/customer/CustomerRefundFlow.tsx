"use client";

import { useState, type FormEvent } from "react";
import { ApiError, lookupOrder, submitRefundRequest } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/format";
import type { Order, RefundReason, RefundSubmission } from "@/lib/types";
import { AppHeader } from "@/components/shared/AppHeader";
import { OutcomeBadge } from "@/components/shared/OutcomeBadge";
import styles from "./CustomerRefundFlow.module.css";

export function CustomerRefundFlow() {
  const [orderNumber, setOrderNumber] = useState("");
  const [order, setOrder] = useState<Order | null>(null);
  const [requestedAmount, setRequestedAmount] = useState("");
  const [reason, setReason] = useState<RefundReason | "">("");
  const [customerMessage, setCustomerMessage] = useState("");
  const [result, setResult] = useState<RefundSubmission | null>(null);
  const [lookupError, setLookupError] = useState("");
  const [submitError, setSubmitError] = useState("");
  const [isLookingUp, setIsLookingUp] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleLookup(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    const normalizedOrderNumber = orderNumber.trim().toUpperCase();
    if (!normalizedOrderNumber) return;

    setIsLookingUp(true);
    setLookupError("");
    setOrder(null);
    setResult(null);

    try {
      const foundOrder = await lookupOrder(normalizedOrderNumber);
      setOrder(foundOrder);
      setOrderNumber(foundOrder.order_number);
      setRequestedAmount(foundOrder.total_amount);
    } catch (error) {
      setLookupError(getErrorMessage(error, "We could not find that order. Check the number and try again."));
    } finally {
      setIsLookingUp(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (!order || !reason || isSubmitting) return;

    setIsSubmitting(true);
    setSubmitError("");

    try {
      const submission = await submitRefundRequest({
        order_id: order.id,
        requested_amount: requestedAmount,
        reason,
        customer_message: customerMessage.trim(),
      });
      setResult(submission);
    } catch (error) {
      setSubmitError(getErrorMessage(error, "We could not submit your request. Please review the details and try again."));
    } finally {
      setIsSubmitting(false);
    }
  }

  function startAnotherRequest(): void {
    setOrder(null);
    setResult(null);
    setOrderNumber("");
    setRequestedAmount("");
    setReason("");
    setCustomerMessage("");
    setLookupError("");
    setSubmitError("");
  }

  return (
    <div className={styles.page}>
      <AppHeader active="customer" />

      <main className={styles.main}>
        <div className={styles.intro}>
          <p className={styles.eyebrow}>CUSTOMER CARE / REFUNDS</p>
          <h1>Let’s make this right.</h1>
          <p className={styles.introText}>
            Find your order and tell us what happened. We’ll review the details against our refund policy.
          </p>
        </div>

        <div className={styles.workflow}>
          <aside className={styles.steps} aria-label="Refund request steps">
            <span className={styles.stepNumber}>01</span><span>Find order</span>
            <span className={styles.stepLine} />
            <span className={styles.stepNumber}>02</span><span>Describe issue</span>
            <span className={styles.stepLine} />
            <span className={styles.stepNumber}>03</span><span>Review result</span>
          </aside>

          <section className={styles.formPanel} aria-label="Refund request">
            {!order && (
              <form className={styles.lookupForm} onSubmit={handleLookup}>
                <div className={styles.sectionHeading}>
                  <span className={styles.sectionIndex}>01</span>
                  <div>
                    <h2>Find your order</h2>
                    <p>Enter the order number from your confirmation.</p>
                  </div>
                </div>
                <label className={styles.label} htmlFor="order-number">Order number</label>
                <div className={styles.lookupRow}>
                  <input
                    id="order-number"
                    autoComplete="off"
                    value={orderNumber}
                    onChange={(event) => setOrderNumber(event.target.value)}
                    placeholder="WN-1001"
                    required
                  />
                  <button className={styles.primaryButton} disabled={isLookingUp} type="submit">
                    {isLookingUp ? "Looking up…" : "Find order"}
                  </button>
                </div>
                <p className={styles.helper}>Try demo order <button className={styles.textButton} onClick={() => setOrderNumber("WN-1001")} type="button">WN-1001</button></p>
                {lookupError && <p className={styles.error} role="alert">{lookupError}</p>}
              </form>
            )}

            {order && !result && (
              <>
                <section className={styles.orderSummary} aria-label="Order found">
                  <div className={styles.summaryHeader}>
                    <div>
                      <p className={styles.sectionIndex}>ORDER FOUND</p>
                      <h2>{order.item_name}</h2>
                    </div>
                    <button className={styles.textButton} onClick={startAnotherRequest} type="button">Change order</button>
                  </div>
                  <dl className={styles.orderFacts}>
                    <div><dt>Order</dt><dd>{order.order_number}</dd></div>
                    <div><dt>Order total</dt><dd>{formatCurrency(order.total_amount)}</dd></div>
                    <div><dt>Placed</dt><dd>{formatDate(order.ordered_at)}</dd></div>
                  </dl>
                  <p className={order.final_sale ? styles.finalSale : styles.saleStatus}>
                    Final sale: {order.final_sale ? "Yes" : "No"}
                  </p>
                </section>

                <form className={styles.requestForm} onSubmit={handleSubmit} aria-label="Refund request">
                  <div className={styles.sectionHeading}>
                    <span className={styles.sectionIndex}>02</span>
                    <div>
                      <h2>Tell us what happened</h2>
                      <p>Your message helps us understand the issue.</p>
                    </div>
                  </div>

                  <label className={styles.label} htmlFor="requested-amount">Requested amount</label>
                  <div className={styles.amountField}>
                    <span aria-hidden="true">$</span>
                    <input
                      id="requested-amount"
                      type="number"
                      min="0.01"
                      max={order.total_amount}
                      step="0.01"
                      value={requestedAmount}
                      onChange={(event) => setRequestedAmount(event.target.value)}
                      required
                    />
                  </div>

                  <label className={styles.label} htmlFor="reason-hint">
                    What best describes the issue? <span className={styles.hintLabel}>Customer-provided hint</span>
                  </label>
                  <select id="reason-hint" value={reason} onChange={(event) => setReason(event.target.value as RefundReason)} required>
                    <option value="">Choose an issue</option>
                    <option value="DAMAGED">Item arrived damaged</option>
                    <option value="INCORRECT_ITEM">I received the wrong item</option>
                    <option value="OTHER">Something else</option>
                  </select>

                  <label className={styles.label} htmlFor="customer-message">Tell us what happened</label>
                  <textarea
                    id="customer-message"
                    value={customerMessage}
                    onChange={(event) => setCustomerMessage(event.target.value)}
                    placeholder="Describe the issue with your order…"
                    rows={5}
                    maxLength={2000}
                    required
                  />
                  <p className={styles.helper}>Your message is analyzed to understand the issue. Refund eligibility is decided by policy.</p>

                  {submitError && <p className={styles.error} role="alert">{submitError}</p>}
                  {isSubmitting && (
                    <p className={styles.processing} role="status" aria-live="polite">
                      <span className={styles.processingMark} aria-hidden="true" />
                      <span><strong>Analyzing your request…</strong><br />Checking your message and refund policy.</span>
                    </p>
                  )}

                  <button className={styles.primaryButton} disabled={isSubmitting} type="submit">
                    {isSubmitting ? "Submitting…" : "Submit refund request"}
                  </button>
                </form>
              </>
            )}

            {result && order && <RefundResult result={result} itemName={order.item_name} onReset={startAnotherRequest} />}
          </section>
        </div>
      </main>
    </div>
  );
}

function RefundResult({
  result,
  itemName,
  onReset,
}: {
  result: RefundSubmission;
  itemName: string;
  onReset: () => void;
}) {
  const nextStep: Record<RefundSubmission["outcome"], string> = {
    APPROVED: "Your request meets the refund policy requirements.",
    DENIED: "Your request does not meet the refund policy requirements.",
    ESCALATED: "Your request needs review by a support specialist.",
  };
  const unavailable = result.ai_analysis_status !== "ANALYZED";

  return (
    <section className={styles.result} aria-live="polite" aria-label="Refund decision">
      <p className={styles.sectionIndex}>03 / REQUEST REVIEW</p>
      <OutcomeBadge outcome={result.outcome} />
      <h2>{result.outcome}</h2>
      <p className={styles.resultExplanation}>{result.explanation}</p>
      <p className={styles.nextStep}>{unavailable && result.outcome === "ESCALATED"
        ? "A support specialist will review your request because automated analysis is temporarily unavailable."
        : nextStep[result.outcome]}</p>
      <p className={styles.authorityNote}>Final decision is determined by refund policy.</p>

      <section className={styles.aiResult} aria-label="AI analysis">
        <p className={styles.sectionIndex}>MESSAGE ANALYSIS</p>
        <h3>AI interpretation</h3>
        {result.ai_analysis ? (
          <>
            <p><strong>Issue identified</strong><br />{result.ai_analysis.classified_reason.replaceAll("_", " ")}</p>
            <p>{result.ai_analysis.summary}</p>
            <p className={styles.suggestedResponse}><strong>Suggested response</strong><br />{result.ai_analysis.suggested_response}</p>
          </>
        ) : (
          <p>{result.ai_analysis_status === "UNAVAILABLE"
            ? "Automated analysis is temporarily unavailable. Your request is being handled according to the policy fallback above."
            : `Message analysis status: ${result.ai_analysis_status.toLowerCase().replaceAll("_", " ")}.`}</p>
        )}
        <p className={styles.authorityNote}>The analysis supports the review; it does not make the refund decision.</p>
      </section>

      <button className={styles.secondaryButton} onClick={onReset} type="button">Start another request</button>
      <span className={styles.itemContext}>Request for {itemName}</span>
    </section>
  );
}

function getErrorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError || error instanceof Error ? error.message : fallback;
}
