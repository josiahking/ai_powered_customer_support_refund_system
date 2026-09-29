"use client";

import { useState, type FormEvent } from "react";
import { ApiError, submitRefundRequest, verifyOrder } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/format";
import type { CustomerOrder, RefundReason, RefundSubmission } from "@/lib/types";
import { AppHeader } from "@/components/shared/AppHeader";
import { OutcomeBadge } from "@/components/shared/OutcomeBadge";
import styles from "./CustomerRefundFlow.module.css";

export function CustomerRefundFlow() {
  const [orderNumber, setOrderNumber] = useState("");
  const [email, setEmail] = useState("");
  const [order, setOrder] = useState<CustomerOrder | null>(null);
  const [orderAccessToken, setOrderAccessToken] = useState("");
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
    const normalizedEmail = email.trim();
    if (!normalizedOrderNumber || !normalizedEmail) return;

    setIsLookingUp(true);
    setLookupError("");
    setOrder(null);
    setOrderAccessToken("");
    setResult(null);

    try {
      const verified = await verifyOrder(normalizedOrderNumber, normalizedEmail);
      setOrder(verified.order);
      setOrderAccessToken(verified.order_access_token);
      setOrderNumber(verified.order.order_number);
      setRequestedAmount(verified.order.total_amount);
    } catch (error) {
      setLookupError(getErrorMessage(error, "We could not verify that order. Check the order number and email and try again."));
    } finally {
      setIsLookingUp(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (!order || !orderAccessToken || !reason || isSubmitting) return;

    setIsSubmitting(true);
    setSubmitError("");

    try {
      const submission = await submitRefundRequest({
        order_access_token: orderAccessToken,
        requested_amount: requestedAmount,
        reason,
        customer_message: customerMessage.trim(),
      });
      setResult(submission);
    } catch (error) {
      if (error instanceof Error && "status" in error && error.status === 403) {
        setOrder(null);
        setOrderAccessToken("");
        setLookupError(error.message);
        return;
      }
      setSubmitError(getErrorMessage(error, "We could not submit your request. Please review the details and try again."));
    } finally {
      setIsSubmitting(false);
    }
  }

  function startAnotherRequest(): void {
    setOrder(null);
    setOrderAccessToken("");
    setResult(null);
    setOrderNumber("");
    setEmail("");
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
                    <p>We use these details to verify the order before accepting a refund request.</p>
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
                    {isLookingUp ? "Verifying…" : "Verify order"}
                  </button>
                </div>
                <label className={styles.label} htmlFor="order-email">Email used for this order</label>
                <input
                  id="order-email"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  maxLength={254}
                  required
                />
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
                  {order.final_sale && (
                    <p className={styles.finalSale}>Final sale — this item is not refundable.</p>
                  )}
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
                  <p className={styles.helper}>We’ll review your message and apply our refund policy.</p>

                  {submitError && <p className={styles.error} role="alert">{submitError}</p>}
                  {isSubmitting && (
                    <p className={styles.processing} role="status" aria-live="polite">
                      <span className={styles.processingMark} aria-hidden="true" />
                      <span><strong>Reviewing your request…</strong><br />Checking your message against our refund policy.</span>
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
  const escalated = result.outcome === "ESCALATED";

  return (
    <section className={styles.result} aria-live="polite" aria-label="Refund decision">
      <p className={styles.sectionIndex}>03 / REQUEST REVIEW</p>
      <OutcomeBadge outcome={result.outcome} />
      <h2>{result.outcome}</h2>
      <p className={styles.resultExplanation}>{escalated
        ? "Your request needs a closer look before we can make a decision."
        : result.explanation}</p>
      <p className={styles.nextStep}>{escalated
        ? "A support specialist will review your request."
        : nextStep[result.outcome]}</p>
      <p className={styles.authorityNote}>Final decision is determined by refund policy.</p>

      <button className={styles.secondaryButton} onClick={onReset} type="button">Start another request</button>
      <span className={styles.itemContext}>Request for {itemName}</span>
    </section>
  );
}

function getErrorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError || error instanceof Error ? error.message : fallback;
}
