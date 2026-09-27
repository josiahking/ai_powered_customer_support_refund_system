import type { RefundOutcome } from "@/lib/types";
import styles from "./OutcomeBadge.module.css";

type OutcomeBadgeProps = {
  outcome: RefundOutcome;
};

const outcomeClass: Record<RefundOutcome, string> = {
  APPROVED: styles.approved,
  DENIED: styles.denied,
  ESCALATED: styles.escalated,
};

export function OutcomeBadge({ outcome }: OutcomeBadgeProps) {
  return <span className={`${styles.badge} ${outcomeClass[outcome]}`}>{outcome}</span>;
}