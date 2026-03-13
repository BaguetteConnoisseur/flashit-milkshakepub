import websocket

def on_open(ws):
    print("WebSocket OPEN")
    ws.send("Hello from Python!")

def on_message(ws, message):
    print("WebSocket MESSAGE:", message)

def on_error(ws, error):
    print("WebSocket ERROR:", error)

def on_close(ws, close_status_code, close_msg):
    print("WebSocket CLOSED", close_status_code, close_msg)

ws = websocket.WebSocketApp(
    "ws://localhost/ws",
    on_open=on_open,
    on_message=on_message,
    on_error=on_error,
    on_close=on_close
)
ws.run_forever()
