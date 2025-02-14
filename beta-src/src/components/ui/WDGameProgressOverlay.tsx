import { Box, Button, Stack } from "@mui/material";
import * as React from "react";
import Season from "../../enums/Season";
import GameOverviewResponse from "../../state/interfaces/GameOverviewResponse";
import GameStatusResponse from "../../state/interfaces/GameStatusResponse";
import ViewedPhaseState from "../../state/interfaces/ViewedPhaseState";
import {
  formatPhaseForDisplay,
  formatPSYForDisplay,
} from "../../utils/formatPhaseForDisplay";
import {
  getGamePhaseSeasonYear,
  getHistoricalPhaseSeasonYear,
} from "../../utils/state/getPhaseSeasonYear";

// Various of the buttons draw with a Z_INDEX of 2
// So we set this overlay to a Z_INDEX of 4 to draw on top of them,
// so that the user isn't scrolling phases and playing with the save and ready buttons
// while this overlay sits on top.
const Z_INDEX = 4;

const centeredStyle = {
  position: "absolute",
  top: "50%",
  left: "50%",
  // justifyContent: "center",
  // alignItems: "center",
  transform: "translate(-50%, -50%)",
  backgroundColor: "rgba(255,255,255,1)",
  p: "10px",
  borderRadius: "5px",
  zIndex: Z_INDEX,
};

const overlayStyle = {
  position: "absolute",
  left: 0,
  top: 0,
  height: "100%",
  width: "100%",
  backgroundColor: "rgba(52,52,52,0.6)",
  zIndex: Z_INDEX,
};

interface WDGameProgressOverlayProps {
  overview: GameOverviewResponse;
  status: GameStatusResponse;
  viewedPhaseState: ViewedPhaseState;
  clickHandler: () => void;
}

const WDGameProgressOverlay: React.FC<WDGameProgressOverlayProps> = function ({
  overview,
  status,
  viewedPhaseState,
  clickHandler,
}) {
  let innerElem;
  if (
    ["Diplomacy", "Retreats", "Builds", "Finished"].includes(overview.phase)
  ) {
    if (status.phases.length <= 1) {
      innerElem = (
        <Stack direction="column" alignItems="center">
          <Box sx={{ m: "4px" }}>Game progressed to a new phase...</Box>
          <Button
            size="large"
            variant="contained"
            color="success"
            onClick={clickHandler}
          >
            View
            {formatPSYForDisplay({
              phase: overview.phase,
              season: overview.season as Season,
              year: overview.year,
            })}
          </Button>
        </Stack>
      );
    } else {
      const stuffToRender: React.ReactElement[] = [];
      for (
        let idx = viewedPhaseState.latestPhaseViewed;
        idx < status.phases.length - 1;
        idx += 1
      ) {
        const psy = getHistoricalPhaseSeasonYear(status, idx);
        stuffToRender.push(
          <Box key={`progressBox${idx}`} sx={{ m: "4px" }}>
            Finished phase ${formatPSYForDisplay(psy)}.
          </Box>,
        );
      }

      const psy = getGamePhaseSeasonYear(
        overview.phase,
        overview.season,
        overview.year,
      );
      stuffToRender.push(
        <Box sx={{ m: "4px" }} key="newPhaseMsgBox">
          Beginning new phase {formatPSYForDisplay(psy)}.
        </Box>,
      );

      const oldPsy = getHistoricalPhaseSeasonYear(
        status,
        viewedPhaseState.latestPhaseViewed,
      );
      const buttonLabel = `View orders for ${formatPSYForDisplay(oldPsy)}`;

      stuffToRender.push(
        <Button
          size="large"
          variant="contained"
          color="success"
          onClick={clickHandler}
          key="newPhaseOverlayButton"
        >
          {buttonLabel}
        </Button>,
      );

      innerElem = (
        <Stack direction="column" alignItems="center">
          {stuffToRender}
        </Stack>
      );
    }
  } else if (overview.phase === "Pre-game") {
    innerElem = (
      <Box>
        <b>Pre-game:</b> Game is waiting to start
      </Box>
    );
  } else if (overview.phase === "Error") {
    innerElem = <Box>Could not load game. You may need to join this game.</Box>;
  } else {
    innerElem = <Box>Game phase is {overview.phase}!</Box>;
  }
  return (
    <>
      <Box sx={overlayStyle} />
      <Box sx={centeredStyle}>{innerElem}</Box>
    </>
  );
};

export default WDGameProgressOverlay;
