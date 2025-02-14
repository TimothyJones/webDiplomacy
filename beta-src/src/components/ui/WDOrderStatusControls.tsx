import * as React from "react";
import { Stack, useTheme } from "@mui/material";
import WDButton from "./WDButton";
import useViewport from "../../hooks/useViewport";
import getDevice from "../../utils/getDevice";
import Device from "../../enums/Device";
import { useAppDispatch, useAppSelector } from "../../state/hooks";
import {
  gameApiSliceActions,
  gameData,
  gameOrdersMeta,
  gameOverview,
  gameStatus,
  gameViewedPhase,
  saveOrders,
} from "../../state/game/game-api-slice";
import UpdateOrder from "../../interfaces/state/UpdateOrder";
import { RootState } from "../../state/store";
import { OrderStatus } from "../../interfaces/state/MemberData";
import OrderSubmission from "../../interfaces/state/OrderSubmission";

enum OrderStatusButton {
  SAVE = "save",
  READY = "ready",
}

interface WDOrderStatsControlsProps {
  orderStatus: OrderStatus;
}

const WDOrderStatusControls: React.FC<WDOrderStatsControlsProps> = function ({
  orderStatus,
}): React.ReactElement {
  const theme = useTheme();
  const [viewport] = useViewport();
  const { data } = useAppSelector(gameData);
  const ordersMeta = useAppSelector(gameOrdersMeta);
  const status = useAppSelector(gameStatus);
  const viewedPhaseState = useAppSelector(gameViewedPhase);
  const savingOrdersInProgress = useAppSelector(
    (state) => state.game.savingOrdersInProgress,
  );

  const viewingCurPhase =
    viewedPhaseState.viewedPhaseIdx >= status.phases.length - 1;

  const currentOrderInProgress = useAppSelector(
    ({ game: { order } }: RootState) => order.inProgress,
  );

  const dispatch = useAppDispatch();
  const device = getDevice(viewport);
  let isMobile: boolean;
  switch (device) {
    case Device.MOBILE:
    case Device.MOBILE_LG:
    case Device.MOBILE_LANDSCAPE:
    case Device.MOBILE_LG_LANDSCAPE:
      isMobile = true;
      break;
    default:
      isMobile = false;
      break;
  }

  const ordersMetaValues = Object.values(ordersMeta);
  const ordersLength = ordersMetaValues.length;
  const ordersSaved = ordersMetaValues.reduce(
    (acc, meta) => acc + +meta.saved,
    0,
  );

  let readyEnabled: boolean;
  let saveEnabled: boolean;
  let readyButtonText: string;
  let saveButtonText: string;

  // orderStatus contains what the server thinks our order status is.
  if (savingOrdersInProgress === "readying") {
    readyEnabled = false;
    saveEnabled = false;
    readyButtonText = "Readying...";
    saveButtonText = "Save";
  } else if (savingOrdersInProgress === "unreadying") {
    readyEnabled = false;
    saveEnabled = false;
    readyButtonText = "Unreadying...";
    saveButtonText = "Save";
  } else if (savingOrdersInProgress === "saving") {
    readyEnabled = false;
    saveEnabled = false;
    readyButtonText = "Ready";
    saveButtonText = "Saving...";
  } else if (orderStatus.Ready) {
    readyEnabled = viewingCurPhase;
    saveEnabled = false;
    readyButtonText = "Unready";
    saveButtonText = "Save";
  } else if (orderStatus.Saved) {
    readyEnabled = viewingCurPhase;
    saveEnabled = ordersLength !== ordersSaved && viewingCurPhase;
    readyButtonText = "Ready";
    saveButtonText = "Save";
  } else if (orderStatus.Completed) {
    readyEnabled = ordersLength !== ordersSaved && viewingCurPhase;
    saveEnabled = ordersLength !== ordersSaved && viewingCurPhase;
    readyButtonText = "Ready";
    saveButtonText = "Save";
  } else {
    readyEnabled = ordersLength !== ordersSaved && viewingCurPhase;
    saveEnabled = viewingCurPhase;
    readyButtonText = "Ready";
    saveButtonText = "Save";
  }

  const doAnimateGlow =
    saveEnabled && ordersLength !== ordersSaved && !currentOrderInProgress;

  const clickButton = (whatButton: OrderStatusButton) => {
    // console.log("Entered save button click");
    // When you click save or ready, it should clear any actively entered order you have going,
    // and/or any of the move input flyover. It doesn't make sense to ready and have the UI
    // stay with a partially-entered order.
    dispatch(gameApiSliceActions.resetOrder());

    if ("currentOrders" in data && "contextVars" in data) {
      const { currentOrders, contextVars } = data;
      if (contextVars && currentOrders) {
        const orderUpdates: UpdateOrder[] = [];
        currentOrders.forEach(
          ({ fromTerrID, id, toTerrID, type: moveType, unitID, viaConvoy }) => {
            const updateReference = ordersMeta[id].update;
            let orderUpdate: UpdateOrder = {
              fromTerrID,
              id,
              toTerrID,
              type: moveType || "",
              unitID,
              viaConvoy,
            };
            if (updateReference) {
              orderUpdate = {
                ...orderUpdate,
                ...updateReference,
              };
            }
            orderUpdates.push(orderUpdate);
          },
        );
        const orderSubmission: OrderSubmission = {
          orderUpdates,
          context: JSON.stringify(contextVars.context),
          contextKey: contextVars.contextKey,
          queryParams: {},
          userIntent: "saving",
        };
        if (whatButton === OrderStatusButton.READY) {
          if (orderStatus.Ready) {
            orderSubmission.queryParams = { notready: "on" };
            orderSubmission.userIntent = "unreadying";
          } else {
            orderSubmission.queryParams = { ready: "on" };
            orderSubmission.userIntent = "readying";
          }
        }
        // console.log({ orderSubmission });
        dispatch(saveOrders(orderSubmission));
      }
    }
  };

  return (
    <Stack
      alignItems="center"
      direction={isMobile ? "column" : "row"}
      spacing={2}
    >
      <WDButton
        color="primary"
        disabled={!saveEnabled}
        onClick={() => saveEnabled && clickButton(OrderStatusButton.SAVE)}
        sx={{
          filter: !saveEnabled
            ? undefined
            : theme.palette.svg.filters.dropShadows[0],
        }}
        doAnimateGlow={doAnimateGlow}
      >
        {saveButtonText}
      </WDButton>
      <WDButton
        color="primary"
        disabled={!readyEnabled}
        onClick={() => readyEnabled && clickButton(OrderStatusButton.READY)}
        sx={{
          filter: !readyEnabled
            ? undefined
            : theme.palette.svg.filters.dropShadows[0],
        }}
      >
        {readyButtonText}
      </WDButton>
    </Stack>
  );
};

export default WDOrderStatusControls;
