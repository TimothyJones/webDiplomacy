import * as React from "react";
import { useEffect, useState } from "react";
import { Avatar, Box, Chip, useTheme } from "@mui/material";
import TimeNotRunningOutIcon from "../../assets/png/icn_phase_countdown_black.png";
import TimeRunningOutIcon from "../../assets/png/icn_phase_countdown_red.png";
import Season from "../../enums/Season";
import formatTime, { getFormattedTimeLeft } from "../../utils/formatTime";
import { formatPSYForDisplay } from "../../utils/formatPhaseForDisplay";
import useViewport from "../../hooks/useViewport";

interface WDCountdownPillProps {
  endTime: number;
  phaseTime: number;
  viewedPhase: string;
  viewedSeason: Season;
  viewedYear: number;
  gamePhase: string;
  gameSeason: Season;
  gameYear: number;
}

const milli = 1000;

const WDCountdownPill: React.FC<WDCountdownPillProps> = function ({
  endTime,
  phaseTime,
  viewedPhase,
  viewedSeason,
  viewedYear,
  gamePhase,
  gameSeason,
  gameYear,
}) {
  const theme = useTheme();
  const endTimeInMilliSeconds = endTime * milli;
  const phaseTimeInMilliSeconds = phaseTime * milli;
  const [viewport] = useViewport();
  const quarterTimeRemaining =
    endTimeInMilliSeconds - phaseTimeInMilliSeconds / 4;

  const [formattedTimeLeft, setFormattedTimeLeft] = useState<string>(
    getFormattedTimeLeft(endTime),
  );

  useEffect(() => {
    const timer = setInterval(() => {
      const newFormattedTimeLeft = getFormattedTimeLeft(endTime);
      setFormattedTimeLeft(newFormattedTimeLeft);
    }, 1000);

    return () => clearInterval(timer);
  }, [endTime]);

  const isTimeRunningOut = +new Date() > quarterTimeRemaining;
  const shouldDisplayGamePhase =
    viewedPhase !== gamePhase ||
    viewedSeason !== gameSeason ||
    viewedYear !== gameYear;
  let chipDisplay = formattedTimeLeft;
  if (viewport.width >= 600) {
    if (shouldDisplayGamePhase) {
      chipDisplay += ` for ${formatPSYForDisplay({
        phase: gamePhase,
        season: gameSeason,
        year: gameYear,
      })}
      `;
    } else {
      chipDisplay += " this phase";
    }
  }

  return (
    <Chip
      avatar={
        <Avatar>
          <img
            src={isTimeRunningOut ? TimeRunningOutIcon : TimeNotRunningOutIcon}
            alt="Countdown icon"
          />
        </Avatar>
      }
      sx={{
        backgroundColor: isTimeRunningOut
          ? theme.palette.error.main
          : theme.palette.primary.main,
        color: theme.palette.secondary.main,
        filter: theme.palette.svg.filters.dropShadows[0],
      }}
      label={chipDisplay}
    />
  );
};

export default WDCountdownPill;
