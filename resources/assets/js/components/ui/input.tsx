import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "!bg-transparent !border-input !text-base !text-foreground !border !rounded-md !shadow-xs !px-3 !py-1 !w-full !h-9 !min-w-0 !transition-[color,box-shadow] !outline-none file:!inline-flex file:!h-7 file:!border-0 file:!bg-transparent file:!text-sm file:!font-medium disabled:!pointer-events-none disabled:!cursor-not-allowed disabled:!opacity-50 md:!text-sm !file:text-foreground !placeholder:text-muted-foreground !selection:bg-primary !selection:text-primary-foreground dark:!bg-input/30",
        "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]",
        "!aria-invalid:ring-destructive/20 dark:!aria-invalid:ring-destructive/40 !aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
