import * as React from "react";
import { cn } from "@/lib/utils";
import { CheckIcon, ChevronDownIcon } from "lucide-react"

// Context for Select
interface SelectOption { value: string; label: string }
interface SelectContextType {
  open: boolean;
  setOpen: React.Dispatch<React.SetStateAction<boolean>>;
  selected: string;
  setSelected: (val: string) => void;
  triggerRef: HTMLButtonElement | null;
  setTriggerRef: (ref: HTMLButtonElement | null) => void;
  highlightedIndex: number;
  setHighlightedIndex: React.Dispatch<React.SetStateAction<number>>;
  itemsRef: React.MutableRefObject<(HTMLDivElement | null)[]>;
  options: SelectOption[];
}
const SelectContext = React.createContext<SelectContextType | null>(null);

function Select({ value, onValueChange, options, children, ...props }: {
  value: string;
  onValueChange: (val: string) => void;
  options: Array<{ value: string; label: string }>;
  children: React.ReactNode;
}) {
  const [open, setOpen] = React.useState(false);
  const [selected, setSelected] = React.useState(value);
  const [triggerRef, setTriggerRef] = React.useState<HTMLButtonElement | null>(null);
  const [highlightedIndex, setHighlightedIndex] = React.useState<number>(-1);
  const itemsRef = React.useRef<(HTMLDivElement | null)[]>([]);

  React.useEffect(() => {
    setSelected(value);
  }, [value]);

  const contextValue = React.useMemo(() => ({
    open,
    setOpen,
    selected,
    setSelected: (val: string) => {
      setSelected(val);
      onValueChange(val);
      setOpen(false);
    },
    triggerRef,
    setTriggerRef,
    highlightedIndex,
    setHighlightedIndex,
    itemsRef,
    options,
  }), [open, selected, onValueChange, highlightedIndex, options]);

  return (
    <SelectContext.Provider value={contextValue}>
      <div className="relative" {...props}>{children}</div>
    </SelectContext.Provider>
  );
}

function SelectTrigger({ children, className, ...props }: React.ComponentProps<'button'>) {
  const ctx = React.useContext(SelectContext)!;
  return (
    <button
      type="button"
      ref={ctx.setTriggerRef}
      className={cn(
        "border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex w-fit items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-9",
        className
      )}
      aria-haspopup="listbox"
      aria-expanded={ctx.open}
      onClick={() => ctx.setOpen((o: boolean) => !o)}
      onKeyDown={e => {
        if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          ctx.setOpen(true);
          ctx.setHighlightedIndex(0);
        }
      }}
      {...props}
    >
      {children}
      <span className="ml-auto text-muted-foreground"><ChevronDownIcon className="size-4 opacity-50" /></span>
    </button>
  );
}

interface SelectItemProps {
  value: string;
  children: React.ReactNode;
  index?: number;
  className?: string;
  [key: string]: any;
}

function SelectItem({ value, children, index, className, ...props }: SelectItemProps) {
  const ctx = React.useContext(SelectContext)!;
  const selected = ctx.selected === value;
  const highlighted = ctx.highlightedIndex === index;
  const ref = React.useRef<HTMLDivElement>(null);
  React.useEffect(() => {
    if (highlighted && ref.current) {
      ref.current.scrollIntoView({ block: "nearest" });
    }
    if (typeof index === 'number') {
      ctx.itemsRef.current[index] = ref.current;
    }
  }, [highlighted, index]);
  return (
    <div
      ref={ref}
      role="option"
      aria-selected={selected}
      tabIndex={-1}
      className={cn(
        "focus:bg-accent focus:text-accent-foreground [&_svg:not([class*='text-'])]:text-muted-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        highlighted && "bg-accent text-accent-foreground",
        className
      )}
      onClick={() => ctx.setSelected(value)}
      onMouseEnter={() => typeof index === 'number' && ctx.setHighlightedIndex(index)}
      data-value={value}
      {...props}
    >
      <span className="absolute right-2 flex size-3.5 items-center justify-center">
        {selected && <CheckIcon className="size-4" />}
      </span>
      <span>{children}</span>
    </div>
  );
}
SelectItem.displayName = 'SelectItem';

function isSelectItemElement(child: any): child is React.ReactElement<SelectItemProps> {
  return (
    React.isValidElement(child) &&
    typeof child.type === 'function' &&
    (child.type as any).displayName === 'SelectItem'
  );
}

function SelectContent({ children, className }: { children: React.ReactNode; className?: string }) {
  const ctx = React.useContext(SelectContext)!;
  if (!ctx.open) return null;
  return (
    <div
      className={cn(
        "bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 max-h-[300px] min-w-[8rem] origin-top overflow-x-hidden overflow-y-auto rounded-md border shadow-md p-1 mt-1",
        className
      )}
      role="listbox"
      tabIndex={-1}
      onKeyDown={e => {
        if (e.key === "ArrowDown") {
          e.preventDefault();
          ctx.setHighlightedIndex((i: number) => Math.min(i + 1, ctx.itemsRef.current.length - 1));
        } else if (e.key === "ArrowUp") {
          e.preventDefault();
          ctx.setHighlightedIndex((i: number) => Math.max(i - 1, 0));
        } else if (e.key === "Enter" && ctx.highlightedIndex >= 0) {
          e.preventDefault();
          const item = ctx.itemsRef.current[ctx.highlightedIndex];
          if (item) item.click();
        } else if (e.key === "Escape") {
          ctx.setOpen(false);
        }
      }}
    >
      {React.Children.map(children, (child, idx) => {
        if (isSelectItemElement(child)) {
          return React.cloneElement(child as React.ReactElement<SelectItemProps>, { index: idx });
        }
        return child;
      })}
    </div>
  );
}

function SelectValue({ placeholder }: { placeholder?: string }) {
  const ctx = React.useContext(SelectContext)!;
  const selectedOption = ctx.options?.find((opt: any) => opt.value === ctx.selected);
  return (
    <span className="truncate">
      {selectedOption ? selectedOption.label : placeholder}
    </span>
  );
}

export { Select, SelectTrigger, SelectContent, SelectItem, SelectValue };
