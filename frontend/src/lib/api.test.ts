import {
  getRefundRequest,
  getSupportSession,
  listRefundRequests,
  loginSupport,
  logoutSupport,
  lookupOrder,
  submitRefundRequest,
} from "./api";

describe("support API credentials", () => {
  const fetchMock = jest.fn() as jest.MockedFunction<typeof fetch>;

  beforeEach(() => {
    fetchMock.mockReset();
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ authenticated: true, data: [], id: 1 }),
    } as Response);
    global.fetch = fetchMock;
  });

  it("includes cookies on support login, session, logout, list, and detail requests", async () => {
    await loginSupport({ username: "support", password: "test-only" });
    await getSupportSession();
    await logoutSupport();
    await listRefundRequests();
    await getRefundRequest("12");

    expect(fetchMock).toHaveBeenCalledTimes(5);
    for (const [, options] of fetchMock.mock.calls) {
      expect(options?.credentials).toBe("include");
    }
  });

  it("leaves public customer requests without forced credentials", async () => {
    await lookupOrder("WN-1001");
    await submitRefundRequest({
      order_id: 1,
      requested_amount: "25.00",
      reason: "DAMAGED",
      customer_message: "The item arrived damaged.",
    });

    expect(fetchMock).toHaveBeenCalledTimes(2);
    for (const [, options] of fetchMock.mock.calls) {
      expect(options?.credentials).not.toBe("include");
    }
  });

  it("exposes unauthorized status to the support gate without backend text", async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 401,
      json: async () => ({ message: "Internal auth detail" }),
    } as Response);

    await expect(getSupportSession()).rejects.toMatchObject({
      status: 401,
      isUnauthorized: true,
      message: "The support service could not complete that request. Please try again.",
    });
  });
});
