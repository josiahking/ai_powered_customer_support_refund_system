import { RefundDetailView } from "@/components/support/RefundDetailView";

export default async function RefundDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <RefundDetailView requestId={id} />;
}